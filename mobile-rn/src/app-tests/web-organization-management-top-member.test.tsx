import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty, orgTwo } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * StageArt Web版 団体管理 Phase: this Phase's own explicit instruction
 * ("Frontendだけで「Ownerです」と決め打ちする実装は禁止") applied to the
 * management top screen itself - a plain MEMBER (orgTwo's fixed
 * `current_person_role: 'MEMBER'`) must not see Owner-only actions
 * (参加申請/招待), and the 団体情報 edit card must render disabled. The
 * server remains the actual authority (each Owner-only screen maps its
 * own 403 - see the edit/membership-requests test files); this only
 * covers the client-side card visibility this screen controls.
 */
describe('Web 団体管理トップ: Member (not Owner)', () => {
  it('hides Owner-only cards and disables 団体情報 for a plain Member', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [orgTwo] },
      { test: (u) => u.endsWith('/projects'), status: 200, body: [] },
      { test: (u) => u.endsWith('/productions'), status: 200, body: [] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/organizations/org-2' });

    await waitFor(() => expect(screen.getByTestId('organization-management-menu')).toBeVisible());
    expect(screen.getByTestId('organization-management-menu-members')).toBeVisible();
    expect(screen.getByTestId('organization-management-menu-productions')).toBeVisible();
    expect(screen.queryByTestId('organization-management-menu-requests')).toBeNull();
    expect(screen.queryByTestId('organization-management-menu-invite')).toBeNull();
    expect(screen.getByTestId('organization-management-menu-edit').props.accessibilityState?.disabled).toBe(true);
  });
});
