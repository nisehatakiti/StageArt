import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty, orgOne, productionOne, projectOne } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/** §24: "Home → Production", "Production → 予定", "Production →
 * Homeへ戻る" (Back Navigation) in one continuous flow. */
describe('Home -> Production Context -> back to Home', () => {
  it('navigates into a Production, shows its Shell, and returns to Home via the explicit back control', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [orgOne] },
      { test: (u) => u.endsWith('/projects'), status: 200, body: [projectOne] },
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne },
      { test: (u) => u.endsWith('/productions'), status: 200, body: [productionOne] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/home' });

    await waitFor(() => expect(screen.getByTestId('production-card-prod-1')).toBeVisible());
    await fireEvent.press(screen.getByTestId('production-card-prod-1'));

    await waitFor(() => expect(screen.getByTestId('back-to-home')).toBeVisible());
    expect(screen.getAllByText('予定').length).toBeGreaterThan(0);
    await waitFor(() => expect(screen.getByText('○○公演2026')).toBeVisible());

    await fireEvent.press(screen.getByTestId('back-to-home'));

    await waitFor(() => expect(screen.getByTestId('production-list')).toBeVisible());
    expect(screen.queryByTestId('back-to-home')).toBeNull();
  });
});
