import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty, orgOne, productionOne, projectOne } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * 公演・活動一覧 is the 公演管理 area. A delegate given only メンバー管理
 * opening the URL directly is refused, even when a Production of theirs
 * exists in this Organization.
 */
describe('Web 公演・活動一覧: 公演管理を任されていない代理人', () => {
  it('is refused', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [{ ...orgOne, current_person_role: 'ORGANIZATION_MANAGER', current_person_management_areas: ['MEMBERS'] }] },
      { test: (u) => u.endsWith('/projects'), status: 200, body: [projectOne] },
      { test: (u) => u.endsWith('/productions'), status: 200, body: [productionOne] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: `/organizations/${orgOne.id}/productions` });

    await waitFor(() => expect(screen.getByTestId('organization-productions-unavailable')).toBeVisible());
    expect(screen.queryByTestId(`organization-production-row-${productionOne.id}`)).toBeNull();
    expect(screen.queryByTestId('organization-productions-create-link')).toBeNull();
  });
});
