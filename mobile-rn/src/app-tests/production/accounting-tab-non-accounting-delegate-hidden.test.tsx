import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { accountingFull, mockFetchRoutes, productionOne } from './__fixtures__/productionShellFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

const ticketManager = { ...productionOne, is_primary_manager: false, delegate_role: 'TICKET_MANAGER', delegate_roles: ['TICKET_MANAGER'] };

/**
 * 会計 (Production-wide Budget / Actual) is for the PrimaryManager or an
 * ACCOUNTING_MANAGER only: a delegate with another Role does not get the
 * tab, and the Accounting Summary is never requested.
 */
describe('Production Shell: 会計 tab for a delegate without ACCOUNTING_MANAGER', () => {
  it('does not list the 会計 tab', async () => {
    mockFetchRoutes([{ test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: ticketManager }]);

    renderRouter('src/app', { initialUrl: '/production/prod-1/schedule' });

    await waitFor(() => expect(screen.getByTestId('production-title')).toHaveTextContent('○○公演2026'));
    expect(screen.getAllByText('予定').length).toBeGreaterThan(0);
    expect(screen.queryByText('会計')).toBeNull();
    const calls = (global.fetch as jest.Mock).mock.calls.map(([url]) => String(url));
    expect(calls.some((url) => url.includes('/productions/prod-1/accounting'))).toBe(false);
  });
});
