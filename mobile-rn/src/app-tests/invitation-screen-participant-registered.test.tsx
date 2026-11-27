import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty, productionOne } from './__fixtures__/homeFixtures';
import { myInvitation } from './__fixtures__/r14bFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * Invitation policy 2026-11-27: opening the invitation mail again after
 * registering (and verifying the address) shows that the person is a
 * participant now, and leads to the Production.
 */
describe('招待の画面（公演）: 登録済み', () => {
  it('says the person is registered and opens the Production', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/me/participant-invitations/invitation-1'), status: 200, body: myInvitation({ status: 'REGISTERED' }) },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
      // The Production screen it opens.
      { test: (u) => u.endsWith('/productions/prod-1/overview'), status: 200, body: productionOne },
      { test: (u) => u.endsWith('/productions/prod-1/delegates/me'), status: 200, body: [] },
      { test: (u) => u.endsWith('/me/participating-productions'), status: 200, body: [] },
    ]);

    const router = renderRouter('src/app', { initialUrl: '/participant-invitations/invitation-1' });

    await waitFor(() => expect(screen.getByTestId('participant-invitation-status-REGISTERED')).toHaveTextContent('この公演の参加者として登録されています。'));
    fireEvent.press(screen.getByTestId('participant-invitation-open-production'));
    await waitFor(() => expect(router.getPathname()).toBe('/production/prod-1/overview'));
  });
});
