import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty, productionOne } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * StageArt Productionメンバー追加(氏名＋メールアドレス統一)ラウンド §12/§19:
 * the member-add section is now a single 氏名＋メールアドレス＋役割＋備考 form -
 * there is no Person ID input, no standalone "search by email" step, and
 * no NAME_ONLY-only add path in this UI anymore (§0/§1/§11).
 */
describe('Web メンバー管理: 統一されたメンバー追加フォーム', () => {
  it('does not render any removed search/name-only UI', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne },
      { test: (u) => u.endsWith('/productions/prod-1/participants'), status: 200, body: [] },
      { test: (u) => u.endsWith('/productions/prod-1/participation-requests'), status: 200, body: [] },
      { test: (u) => u.endsWith('/productions/prod-1/participant-invitations'), status: 200, body: [] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/productions/prod-1/participants' });

    await waitFor(() => expect(screen.getByTestId('production-participants-new-member-name')).toBeVisible());
    expect(screen.getByTestId('production-participants-new-member-email')).toBeVisible();
    expect(screen.getByTestId('production-participants-new-member-type-CAST')).toBeVisible();
    expect(screen.getByTestId('production-participants-new-member-type-STAFF')).toBeVisible();
    expect(screen.getByTestId('production-participants-new-member-remarks')).toBeVisible();
    expect(screen.getByTestId('production-participants-add-member')).toBeVisible();

    expect(screen.queryByTestId('production-participants-person-id-input')).toBeNull();
    expect(screen.queryByTestId('production-participants-person-search')).toBeNull();
    expect(screen.queryByTestId('production-participants-email-input')).toBeNull();
    expect(screen.queryByTestId('production-participants-email-search')).toBeNull();
    expect(screen.queryByTestId('production-participants-new-name')).toBeNull();
    expect(screen.queryByTestId('production-participants-add')).toBeNull();
  });
});
