import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty, orgOne } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/** A delegate given 会計管理 reaches the 会計管理 screen. */
describe('Web 団体管理: 会計管理を任された代理人', () => {
  it('reaches the screen', async () => {
    mockFetchRoutes([
      {
        test: (u) => u.endsWith('/organizations'),
        status: 200,
        body: [{ ...orgOne, accounting_enabled: true, current_person_role: 'ORGANIZATION_MANAGER', current_person_management_areas: ['ACCOUNTING'] }],
      },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: `/organizations/${orgOne.id}/accounting` });

    await waitFor(() => expect(screen.getByTestId('organization-accounting-title')).toBeVisible());
    expect(screen.queryByTestId('organization-accounting-unavailable')).toBeNull();
  });
});
