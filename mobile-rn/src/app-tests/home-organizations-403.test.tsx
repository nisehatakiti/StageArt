import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/** §17/§24: 403 (Authorization) must render a distinct message from 401
 * (Authentication) - see home-organizations-401.test.tsx for the
 * counterpart. */
describe('Home: Organization list fails with 403', () => {
  it('shows an Authorization-specific error message', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 403, body: { code: 'stageart_forbidden', message: 'Forbidden' } },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/home' });

    await waitFor(() => expect(screen.getByTestId('organizations-error')).toBeVisible());
    expect(screen.getByText('この情報を表示する権限がありません。')).toBeVisible();
  });
});
