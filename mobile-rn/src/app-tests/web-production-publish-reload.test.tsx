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
 * StageArt Web版 公演管理 Phase: 「下書き → 公開 → リロード → 公開済み」の
 * リロード側の再現。この画面は published_at をローカルstateに一切持たず、
 * 毎回 useProduction(id) の実フェッチ結果だけを描画する（publish.tsx's own
 * docblock）ため、「公開直後」と「公開後にリロードした状態」は実装上
 * 区別が無く、どちらも同じ「GET が published_at 付きで返ってくる」状態を
 * 描画するだけになる - このテストはその再現であり、
 * web-production-publish-action.test.tsx（公開する操作そのもの）を補完する。
 */
describe('Web 公開設定: 公開後にリロードしても公開済み状態と公開URLが維持される', () => {
  it('shows 公開中 and the real public page URL from a fresh GET after a simulated reload', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [{ ...orgOne, slug: 'kujira-theatre' }] },
      { test: (u) => u.endsWith('/projects'), status: 200, body: [projectOne] },
      {
        test: (u) => u.endsWith('/productions/prod-1'),
        status: 200,
        body: { ...productionOne, slug: 'kappa-homerun', published_at: '2026-08-25T09:00:00+09:00' },
      },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/productions/prod-1/publish' });

    await waitFor(() => expect(screen.getByTestId('production-publish-status-pill')).toBeVisible());
    expect(screen.getByText('公開中')).toBeVisible();
    // The public URL depends on a second, independent fetch (this
    // Production's own Organization, resolved via Project) settling
    // after the Production fetch above already has - wait for it
    // separately rather than asserting synchronously right after.
    await waitFor(() => expect(screen.getByTestId('production-publish-view-public')).toBeVisible());
    expect(screen.getByText('公開ページを見る（/kujira-theatre/kappa-homerun）')).toBeVisible();
    expect(screen.queryByTestId('production-publish-button')).toBeNull();
  });
});
