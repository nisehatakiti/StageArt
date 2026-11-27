import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * Invitation policy 2026-11-27: an invitation that is not the logged-in
 * account's (another address, an unverified one, or none at all) is the
 * server's 404 - nothing of it is shown, only the common notice and the way
 * Home.
 */
describe('招待の画面（公演）: 表示できない招待', () => {
  it('shows only the common notice and leads Home', async () => {
    mockFetchRoutes([
      {
        test: (u) => u.endsWith('/me/participant-invitations/someone-elses'),
        status: 404,
        body: { code: 'stageart_participant_invitation_not_found', message: 'The invitation was not found.', data: { status: 404 } },
      },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    const router = renderRouter('src/app', { initialUrl: '/participant-invitations/someone-elses' });

    await waitFor(() =>
      expect(screen.getByTestId('participant-invitation-not-shown')).toHaveTextContent(
        'この招待は表示できません。招待メールを受け取ったメールアドレスでログインしているか確認してください。'
      )
    );
    expect(screen.queryByTestId('participant-invitation-details')).toBeNull();

    fireEvent.press(screen.getByTestId('participant-invitation-home'));
    await waitFor(() => expect(router.getPathname()).toBe('/home'));
  });
});
