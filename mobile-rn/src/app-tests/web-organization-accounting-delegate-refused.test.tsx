import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty, orgOne } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/** 会計管理 is its own area: a delegate given メンバー管理 and 公演管理 but not 会計管理 is refused by URL too. */
describe('Web 団体管理: 会計管理を任されていない代理人', () => {
  it('is refused', async () => {
    mockFetchRoutes([
      {
        test: (u) => u.endsWith('/organizations'),
        status: 200,
        body: [{ ...orgOne, accounting_enabled: true, current_person_role: 'ORGANIZATION_MANAGER', current_person_management_areas: ['MEMBERS', 'PRODUCTIONS'] }],
      },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: `/organizations/${orgOne.id}/accounting` });

    await waitFor(() => expect(screen.getByTestId('organization-accounting-unavailable')).toBeVisible());
    expect(screen.queryByTestId('organization-accounting-placeholder')).toBeNull();
  });
});
