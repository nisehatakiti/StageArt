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
describe('所属団体 (Organization list) fails with 401', () => {
  it('shows an Authentication-specific error message', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 401, body: { code: 'stageart_unauthorized', message: 'Unauthorized' } },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    // R7: 3113937 (2026-10-06) moved the Organization list off Home to the
    // 「所属団体」 menu entry (/organizations), with the same states.
    renderRouter('src/app', { initialUrl: '/organizations' });

    await waitFor(() => expect(screen.getByTestId('organizations-list-error')).toBeVisible());
    expect(screen.getByText('認証が切れました。再度ログインしてください。')).toBeVisible();
  });
});
