import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/** §17/§24: 401 (Authentication) must render a distinct message from
 * 403 (Authorization) - see home-organizations-403.test.tsx for the
 * counterpart, kept in a separate file for the same reason as the
 * other split Home tests. */
describe('Home: Organization list fails with 401', () => {
  it('shows an Authentication-specific error message', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 401, body: { code: 'stageart_unauthorized', message: 'Unauthorized' } },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/home' });

    await waitFor(() => expect(screen.getByTestId('organizations-error')).toBeVisible());
    expect(screen.getByText('認証が切れました。再度ログインしてください。')).toBeVisible();
  });
});
