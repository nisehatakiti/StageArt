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
 *
 * R7: 3bfbce8 (2026-10-07, "reuse normal create flow for past
 * productions") made this a legacy compatibility route: a past Production
 * is created with the same form as every other one, so the route
 * redirects to it instead of rendering a separate placeholder screen.
 */
describe('Web 団体管理: 公演管理 > 過去公演を登録する', () => {
  it('redirects the legacy 過去公演を登録する route to the normal Production creation form', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [orgOne] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: `/organizations/${orgOne.id}/productions/create-past` });

    // The normal creation form (organizations/[id]/productions/create.tsx).
    await waitFor(() => expect(screen.getByTestId('create-production-name')).toBeVisible());
    expect(screen.getByTestId('create-production-submit')).toBeVisible();
    expect(screen.queryByTestId('organization-productions-create-past-title')).toBeNull();
  });
});
