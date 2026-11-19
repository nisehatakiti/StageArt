import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * Production Join Keys are PrimaryManager-only on the server. Someone
 * else opening /production-invite/{id} directly gets the refusal, no code
 * and no 発行 button; the refused 参加申請 list shows its error too.
 */
describe('Web 公演の参加コード: 主担当者以外', () => {
  it('shows the refusal and no code or issue button', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [] },
      {
        test: (u) => u.endsWith('/productions/prod-1/join-keys'),
        status: 403,
        body: { code: 'stageart_production_access_denied', message: 'Only the PrimaryManager can view Join Keys.', data: { status: 403 } },
      },
      { test: (u) => u.endsWith('/productions/prod-1/participation-requests'), status: 403, body: { code: 'stageart_production_access_denied', message: 'denied', data: { status: 403 } } },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/production-invite/prod-1' });

    await waitFor(() => expect(screen.getByTestId('production-join-key-error')).toBeVisible());
    expect(screen.queryByTestId('production-join-key-issue')).toBeNull();
    expect(screen.queryByTestId('production-join-key-active')).toBeNull();
    // The refused 参加申請 list says so instead of claiming there are no requests.
    await waitFor(() => expect(screen.getByTestId('pending-participation-error')).toBeVisible());
    expect(screen.queryByTestId('pending-participation-empty')).toBeNull();
  });
});
