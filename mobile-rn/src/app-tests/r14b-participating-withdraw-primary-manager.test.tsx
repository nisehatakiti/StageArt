import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty } from './__fixtures__/homeFixtures';
import { apiError } from './__fixtures__/r14bFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * PR #7 R14-B stage 9 (design doc §7.2 / §10; confirmed by the user): a
 * Production missing from GET /productions (ARCHIVED / CANCELLED) shows the
 * button; when the person is its PrimaryManager the Backend's 409 is shown
 * as the hand-over notice.
 */
describe('R14-B 参加公演: 一覧にない公演の PrimaryManager', () => {
  it('shows the hand-over notice from the 409', async () => {
    mockFetchRoutes([
      {
        test: (u) => u.endsWith('/me/participating-productions'),
        status: 200,
        body: [{ participant_id: 'participant-9', production_id: 'prod-9', production_name: '終了公演', production_slug: null, participant_type: 'STAFF' }],
      },
      { test: (u) => u.endsWith('/productions'), status: 200, body: [] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/participating-productions' });

    await waitFor(() => expect(screen.getByTestId('participating-production-withdraw-prod-9')).toBeVisible());
    await fireEvent.press(screen.getByTestId('participating-production-withdraw-prod-9'));
    await waitFor(() => expect(screen.getByTestId('participating-production-withdraw-confirm-prod-9')).toBeVisible());

    (global.fetch as jest.Mock).mockImplementationOnce(apiError(409, 'stageart_participant_withdraw_primary_manager'));
    await fireEvent.press(screen.getByTestId('participating-production-withdraw-confirm-prod-9'));
    await waitFor(() =>
      expect(screen.getByTestId('participating-production-error-prod-9')).toHaveTextContent(
        '公演の管理者（PrimaryManager）は参加をやめられません。先に管理者を引き継いでください。'
      )
    );
  });
});
