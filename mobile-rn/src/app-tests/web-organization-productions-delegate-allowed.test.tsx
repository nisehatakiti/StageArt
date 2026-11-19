import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty, orgOne, productionOne, projectOne } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/** A delegate given 公演管理 gets the 公演・活動一覧 screen and its 作成 link. */
describe('Web 公演・活動一覧: 公演管理を任された代理人', () => {
  it('sees the list and the create link', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [{ ...orgOne, current_person_role: 'ORGANIZATION_MANAGER', current_person_management_areas: ['PRODUCTIONS'] }] },
      { test: (u) => u.endsWith('/projects'), status: 200, body: [projectOne] },
      { test: (u) => u.endsWith('/productions'), status: 200, body: [productionOne] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: `/organizations/${orgOne.id}/productions` });

    await waitFor(() => expect(screen.getByTestId(`organization-production-row-${productionOne.id}`)).toBeVisible());
    expect(screen.getByTestId('organization-productions-create-link')).toBeVisible();
    expect(screen.queryByTestId('organization-productions-unavailable')).toBeNull();
  });
});
