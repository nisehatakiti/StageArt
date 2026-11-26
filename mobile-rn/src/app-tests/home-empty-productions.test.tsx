import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty, orgOne, projectOne } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

describe('Home: Organization with no Productions', () => {
  it('shows a natural Empty State, never mistaken for an error', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [orgOne] },
      { test: (u) => u.endsWith('/projects'), status: 200, body: [projectOne] },
      { test: (u) => u.endsWith('/productions'), status: 200, body: [] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    // R7: 3113937 (2026-10-06) moved an Organization's Productions off
    // Home to its 公演・活動一覧 (/organizations/{id}/productions), which
    // has the same empty / error states.
    renderRouter('src/app', { initialUrl: `/organizations/${orgOne.id}/productions` });

    await waitFor(() => expect(screen.getByTestId('organization-productions-empty')).toBeVisible());
    expect(screen.getByText(/まだ公演・活動がありません/)).toBeVisible();
    expect(screen.queryByTestId('organization-productions-list-error')).toBeNull();
  });
});
