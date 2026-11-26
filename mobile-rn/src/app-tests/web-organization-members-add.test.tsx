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
 * StageArt Organization管理UI/UX統一 §3: メンバー追加は現在メンバー管理の
 * テーブル最下部にインラインで統合されている(see
 * web-organization-members.test.tsx の "adds a member inline..." テスト)。
 * この旧ルート(organizations/[id]/members/add.tsx)は、直接アクセスされた
 * 場合にメンバー管理へリダイレクトするだけの Legacy route になった。
 */
describe('Web 団体管理: メンバーを追加 (legacy route)', () => {
  it('redirects to メンバー管理 when visited directly', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [orgOne] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
      { test: (u) => u.endsWith(`/organizations/${orgOne.id}/members`), status: 200, body: [] },
      { test: (u) => u.endsWith(`/organizations/${orgOne.id}/invitations`), status: 200, body: [] },
    ]);

    renderRouter('src/app', { initialUrl: `/organizations/${orgOne.id}/members/add` });

    await waitFor(() => expect(screen.getByTestId('organization-members-list')).toBeVisible());
  });
});
