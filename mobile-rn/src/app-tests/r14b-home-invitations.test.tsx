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
 * PR #7 R14-B stage 9 (design doc §10 「自分宛ての招待（ホーム）」): Home
 * shows the number of invitations that can be accepted (can_accept=true
 * only) and leads to the list.
 */
describe('R14-B ホーム: 承諾待ちの招待', () => {
  it('shows the count of acceptable invitations and opens the list', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
      { test: (u) => u.endsWith('/me/notifications'), status: 200, body: [] },
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [] },
      { test: (u) => u.endsWith('/projects'), status: 200, body: [] },
      { test: (u) => u.endsWith('/productions'), status: 200, body: [] },
      {
        test: (u) => u.endsWith('/me/participant-invitations'),
        status: 200,
        body: [myInvitation(), myInvitation({ id: 'invitation-2' }), myInvitation({ id: 'invitation-3', can_accept: false })],
      },
    ]);

    const router = renderRouter('src/app', { initialUrl: '/home' });

    await waitFor(() => expect(screen.getByTestId('home-participant-invitations')).toBeVisible());
    expect(screen.getByText('承諾待ちの招待が2件あります')).toBeVisible();

    fireEvent.press(screen.getByTestId('home-participant-invitations-link'));
    await waitFor(() => expect(router.getPathname()).toBe('/participant-invitations'));
  });
});
