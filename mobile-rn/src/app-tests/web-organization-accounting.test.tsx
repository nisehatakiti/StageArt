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
 * StageArt Organization Context Menu仕様整合フェーズ2: 会計管理
 * (docs/03-PublicPageURLAndPublicationSchedule.md「Organization Context
 * Menu」) - confirms the route is reachable and renders its title +
 * 準備中 placeholder via OrganizationPlaceholderScreen. Deliberately not
 * reusing production/[id]/accounting.tsx - see the screen's own docblock.
 */
describe('Web 団体管理: 会計管理', () => {
  it('renders the 会計管理 screen skeleton', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [{ ...orgOne, accounting_enabled: true }] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: `/organizations/${orgOne.id}/accounting` });

    await waitFor(() => expect(screen.getByTestId('organization-accounting-title')).toBeVisible());
    expect(screen.getByTestId('organization-accounting-placeholder')).toBeVisible();
  });
});
