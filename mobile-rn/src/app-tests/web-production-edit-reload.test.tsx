import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * StageArt Web版 公演管理 Phase: 編集後リロード. A real browser reload is,
 * from this screen's own perspective, exactly a fresh mount that
 * refetches `useProduction(id)` from scratch - this screen never keeps
 * the edited fields in any state that could survive (or fail to
 * survive) a reload on its own, it always reads straight from the last
 * GET /productions/{id} response. Mounting fresh against a GET that
 * already reflects a previously-saved name (as it would after a real
 * save + reload) is therefore a faithful simulation of "保存 →
 * リロード" without needing a real browser reload event.
 */
describe('Web 公演情報編集: 保存後にリロードしても保存結果が維持される', () => {
  it('shows the already-saved name/slug/title_heading from a fresh GET, not any stale local state', async () => {
    mockFetchRoutes([
      {
        test: (u) => u.endsWith('/productions/prod-1'),
        status: 200,
        body: {
          id: 'prod-1',
          project_id: 'proj-1',
          name: '保存済みの公演名',
          title_heading: '保存済みの肩書',
          status: 'ACTIVE',
          slug: 'saved-slug',
          published_at: null,
          primary_manager_person_id: 'person-1',
          created_at: '',
          updated_at: '',
          is_primary_manager: true,
          delegate_role: null,
        },
      },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/productions/prod-1/edit' });

    await waitFor(() => expect(screen.getByTestId('production-edit-name')).toBeVisible());
    expect(screen.getByTestId('production-edit-name').props.value).toBe('保存済みの公演名');
    expect(screen.getByTestId('production-edit-title-heading').props.value).toBe('保存済みの肩書');
    expect(screen.getByTestId('production-edit-slug').props.value).toBe('saved-slug');
  });
});
