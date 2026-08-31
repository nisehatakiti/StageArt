import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty, orgOne } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * StageArt Web版 団体管理 Phase: 団体管理トップ - an Organization Owner
 * (orgOne's fixed `current_person_role: 'OWNER'`) sees every management
 * card, plus an explicit "公演を作成する" call to action when the
 * Organization has no Production yet (this Phase's own "次に何をすればいい
 * のか迷わないUI" requirement) that actually navigates to the existing,
 * already-working creation screen - not a new mock screen.
 */
describe('Web 団体管理トップ: Owner', () => {
  it('shows every management card and a working 公演を作成する call to action when there are no Productions yet', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [orgOne] },
      { test: (u) => u.endsWith('/projects'), status: 200, body: [] },
      { test: (u) => u.endsWith('/productions'), status: 200, body: [] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/organizations/org-1' });

    await waitFor(() => expect(screen.getByTestId('organization-management-menu')).toBeVisible());
    expect(screen.getByTestId('organization-management-menu-edit')).toBeVisible();
    expect(screen.getByTestId('organization-management-menu-members')).toBeVisible();
    expect(screen.getByTestId('organization-management-menu-requests')).toBeVisible();
    expect(screen.getByTestId('organization-management-menu-invite')).toBeVisible();
    expect(screen.getByTestId('organization-management-menu-productions')).toBeVisible();

    await waitFor(() => expect(screen.getByTestId('organization-management-no-productions')).toBeVisible());
    fireEvent.press(screen.getByTestId('organization-management-create-production'));

    await waitFor(() => expect(screen.getByTestId('create-production-name')).toBeVisible());
  });
});
