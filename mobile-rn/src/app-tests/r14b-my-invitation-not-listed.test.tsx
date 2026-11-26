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

/** PR #7 R14-B stage 9 (design doc §10 N5, §14.2): an id that is not in the own list. */
describe('R14-B 招待確認: 一覧にない招待', () => {
  it('shows only the common notice and leads back to the list', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/me/participant-invitations'), status: 200, body: [myInvitation()] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    const router = renderRouter('src/app', { initialUrl: '/participant-invitations/someone-elses' });

    await waitFor(() => expect(screen.getByTestId('participant-invitation-not-shown')).toHaveTextContent('この招待は表示できません。招待一覧から確認してください。'));
    expect(screen.queryByTestId('participant-invitation-details')).toBeNull();
    expect(screen.queryByText('踊れチュパカブラ')).toBeNull();

    fireEvent.press(screen.getByTestId('participant-invitation-back'));
    await waitFor(() => expect(router.getPathname()).toBe('/participant-invitations'));
  });
});
