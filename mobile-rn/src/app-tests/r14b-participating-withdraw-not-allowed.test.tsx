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
 * PR #7 R14-B stage 9 (design doc §7.2): a row that is not ACTIVE any more
 * (e.g. changed elsewhere) is refused with 409 withdraw_not_allowed, shown
 * on the row.
 */
describe('R14-B 参加公演: 参加中ではない', () => {
  it('shows the 409 withdraw_not_allowed', async () => {
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
    // Not a delegate (as far as GET /productions tells): no note about delegate roles.
    expect(screen.queryByTestId('participating-production-delegate-note-prod-9')).toBeNull();

    (global.fetch as jest.Mock).mockImplementationOnce(apiError(409, 'stageart_participant_withdraw_not_allowed'));
    await fireEvent.press(screen.getByTestId('participating-production-withdraw-confirm-prod-9'));
    await waitFor(() =>
      expect(screen.getByTestId('participating-production-error-prod-9')).toHaveTextContent(
        '現在参加中ではないため、参加をやめる操作はできません。'
      )
    );
  });
});
