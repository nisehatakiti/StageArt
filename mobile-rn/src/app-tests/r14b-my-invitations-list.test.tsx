import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty } from './__fixtures__/homeFixtures';
import { myInvitation } from './__fixtures__/r14bFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * PR #7 R14-B stage 9 (design doc §10 「招待一覧」, §14.2): after login the
 * list shows GET /me/participant-invitations only, an invitation that
 * cannot be accepted says so, and a row opens the invitation.
 */
describe('R14-B 招待一覧', () => {
  it('lists the own invitations and opens one', async () => {
    mockFetchRoutes([
      {
        test: (u) => u.endsWith('/me/participant-invitations'),
        status: 200,
        body: [
          myInvitation(),
          myInvitation({ id: 'invitation-2', production_name: '終了した公演', can_accept: false, unavailable_reason: 'PRODUCTION_CLOSED', effective_status: 'PRODUCTION_CLOSED' }),
        ],
      },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/participant-invitations' });

    await waitFor(() => expect(screen.getByTestId('participant-invitations-list')).toBeVisible());
    expect(screen.getByText('踊れチュパカブラ')).toBeVisible();
    expect(screen.getAllByText('劇団テスト')).toHaveLength(2);
    expect(screen.queryByTestId('participant-invitation-unavailable-invitation-1')).toBeNull();
    expect(screen.getByTestId('participant-invitation-unavailable-invitation-2')).toHaveTextContent('公演が終了または中止されたため、承諾できません');

    fireEvent.press(screen.getByTestId('participant-invitation-invitation-1'));
    await waitFor(() => expect(screen.getByTestId('participant-invitation-details')).toBeVisible());
  });
});
