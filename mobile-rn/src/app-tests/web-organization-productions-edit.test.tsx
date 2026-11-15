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
 * StageArt UI再構成 instruction (this round): 公演管理 > 公演を編集する
 * (docs/03-PublicPageURLAndPublicationSchedule.md「Organization Context
 * Menu」) had no confirmed "pick a Production then edit" flow, so rather
 * than stay a dead-end 準備中 placeholder, it now redirects to the real,
 * already-implemented 公演一覧 screen - the exact destination its own
 * former placeholder text already promised.
 */
describe('Web 団体管理: 公演管理 > 公演を編集する', () => {
  it('redirects to the real 公演一覧 screen', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [orgOne] },
      { test: (u) => u.endsWith('/productions'), status: 200, body: [] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: `/organizations/${orgOne.id}/productions/edit` });

    await waitFor(() => expect(screen.getByTestId('organization-productions-create-link')).toBeVisible());
  });
});
