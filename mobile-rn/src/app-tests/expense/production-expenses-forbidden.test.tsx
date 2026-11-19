import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { expenseFixture, mockFetchRoutes, ticketManagerProduction } from './__fixtures__/expenseFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));


describe('経費申請 list: a delegate without accounting rights', () => {
  it('is refused and nothing is fetched', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: ticketManagerProduction },
      { test: (u) => u.includes('/productions/prod-1/expenses'), status: 200, body: [expenseFixture()] },
    ]);

    renderRouter('src/app', { initialUrl: '/productions/prod-1/expenses' });

    await waitFor(() => expect(screen.getByTestId('production-expenses-forbidden')).toBeVisible());
    const calls = (global.fetch as jest.Mock).mock.calls.map(([url]) => String(url));
    expect(calls.some((url) => url.includes('/productions/prod-1/expenses'))).toBe(false);
  });
});
