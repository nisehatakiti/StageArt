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
describe('所属団体 (Organization list) fails with 403', () => {
  it('shows an Authorization-specific error message', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 403, body: { code: 'stageart_forbidden', message: 'Forbidden' } },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    // R7: 3113937 (2026-10-06) moved the Organization list off Home to the
    // 「所属団体」 menu entry (/organizations), with the same states.
    renderRouter('src/app', { initialUrl: '/organizations' });

    await waitFor(() => expect(screen.getByTestId('organizations-list-error')).toBeVisible());
    expect(screen.getByText('この情報を表示する権限がありません。')).toBeVisible();
  });
});
