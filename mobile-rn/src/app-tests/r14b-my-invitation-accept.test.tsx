import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty } from './__fixtures__/homeFixtures';
import { apiAnswer, callsTo, myInvitation } from './__fixtures__/r14bFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * PR #7 R14-B stage 9 (design doc §10 「招待確認」 N5, D7, §14.2): opening
 * the invitation directly (no list in memory) fetches the own list again;
 * the screen shows the D7 items and what is shared, and 「参加する」 sends
 * POST .../accept and shows the result from the API's answer.
 */
describe('R14-B 招待確認: 承諾', () => {
  it('refetches the list, shows the invitation and accepts it', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/me/participant-invitations'), status: 200, body: [myInvitation({ already_participant: true })] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
      { test: (u) => u.endsWith('/me/participating-productions'), status: 200, body: [] },
      { test: (u) => u.endsWith('/productions'), status: 200, body: [] },
    ]);

    renderRouter('src/app', { initialUrl: '/participant-invitations/invitation-1' });

    await waitFor(() => expect(screen.getByTestId('participant-invitation-details')).toBeVisible());
    expect(callsTo('/me/participant-invitations', 'GET').length).toBeGreaterThan(0);
    expect(screen.getByText('踊れチュパカブラ')).toBeVisible();
    expect(screen.getByText('団体: 劇団テスト')).toBeVisible();
    expect(screen.getByText('役割: 出演者')).toBeVisible();
    expect(screen.getByText('招待者: 制作 花子')).toBeVisible();
    expect(screen.getByTestId('participant-invitation-sharing')).toBeVisible();
    expect(screen.getByTestId('participant-invitation-already-participant')).toBeVisible();

    (global.fetch as jest.Mock).mockImplementationOnce(apiAnswer(200, { participant_id: 'participant-1', production_id: 'prod-1', status: 'ACTIVE' }));
    fireEvent.press(screen.getByTestId('participant-invitation-accept'));

    await waitFor(() => expect(screen.getByTestId('participant-invitation-accepted')).toHaveTextContent('参加しました。公演のメンバーになりました。'));
    expect(callsTo('/participant-invitations/invitation-1/accept', 'POST')).toHaveLength(1);
  });
});
