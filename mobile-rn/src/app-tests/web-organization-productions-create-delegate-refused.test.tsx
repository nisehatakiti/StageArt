import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty, orgOne, projectOne } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * 公演・活動を作る (CreateProductionUseCase) is the 公演管理 area: a
 * delegate given only 会計管理 opening the URL directly gets no form.
 */
describe('Web 公演・活動を作る: 公演管理を任されていない代理人', () => {
  it('is refused and gets no form', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [{ ...orgOne, current_person_role: 'ORGANIZATION_MANAGER', current_person_management_areas: ['ACCOUNTING'] }] },
      { test: (u) => u.endsWith('/projects'), status: 200, body: [projectOne] },
      { test: (u) => u.endsWith('/productions'), status: 200, body: [] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: `/organizations/${orgOne.id}/productions/create` });

    await waitFor(() => expect(screen.getByTestId('organization-productions-create-unavailable')).toBeVisible());
    expect(screen.queryByText('公演・活動を作る')).toBeNull();
  });
});
