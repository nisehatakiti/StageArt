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
 * StageArt Organization Context Menu仕様整合フェーズ2: 公開ページ管理 >
 * SNS (docs/03-PublicPageURLAndPublicationSchedule.md「Organization
 * Context Menu」) - confirms the route is reachable and renders its
 * title + 準備中 placeholder via OrganizationPlaceholderScreen.
 */
describe('Web 団体管理: 公開ページ管理 > SNS', () => {
  it('renders the SNS screen skeleton', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [orgOne] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: `/organizations/${orgOne.id}/sns` });

    await waitFor(() => expect(screen.getByTestId('organization-sns-title')).toBeVisible());
    expect(screen.getByTestId('organization-sns-placeholder')).toBeVisible();
  });
});
