import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty, orgOne } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * StageArt Organization Context Menu仕様整合フェーズ2: 公演管理 >
 * 過去公演を登録する (docs/03-PublicPageURLAndPublicationSchedule.md
 * 「Organization Context Menu」) - confirms the route is reachable and
 * renders its title + 準備中 placeholder via OrganizationPlaceholderScreen,
 * separate from the existing 公演を作る screen.
 */
describe('Web 団体管理: 公演管理 > 過去公演を登録する', () => {
  it('renders the 過去公演を登録する screen skeleton', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [orgOne] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: `/organizations/${orgOne.id}/productions/create-past` });

    await waitFor(() => expect(screen.getByTestId('organization-productions-create-past-title')).toBeVisible());
    expect(screen.getByTestId('organization-productions-create-past-placeholder')).toBeVisible();
  });
});
