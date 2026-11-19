import { fireEvent, screen, waitFor } from '@testing-library/react-native';

import { orgOne } from './__fixtures__/homeFixtures';
import { ORG_MENU_ITEMS, renderSidebarAs, shownOrganizationMenuItems } from './__fixtures__/sidebarRoleHarness';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

const mockPush = jest.fn();
jest.mock('expo-router', () => ({
  ...jest.requireActual('expo-router'),
  usePathname: () => '/organizations/org-1/members',
  useRouter: () => ({ push: mockPush, replace: jest.fn(), back: jest.fn() }),
}));

/**
 * The Organization Context sidebar lists only what each role may use
 * (features/organization/permissions.ts, mirroring the backend's RoleKey
 * allow-lists). Owner: every item, 代表者交代 and 会計管理 included.
 * (Delegate / member / outsider: the web-sidebar-organization-menu-*
 * sibling files.)
 */
describe('Web sidebar: Organization management items for the Owner', () => {
  it('shows every item and each opens its screen', async () => {
    renderSidebarAs([{ ...orgOne, current_person_role: 'OWNER', accounting_enabled: true }]);

    await waitFor(() => expect(screen.getByTestId('web-chrome-nav-organization-info')).toBeVisible());
    expect(shownOrganizationMenuItems()).toEqual(ORG_MENU_ITEMS);
    fireEvent.press(screen.getByTestId('web-chrome-nav-organization-members-owner-transfer'));
    expect(mockPush).toHaveBeenLastCalledWith(`/organizations/${orgOne.id}/members/owner-transfer`);
    fireEvent.press(screen.getByTestId('web-chrome-nav-organization-info'));
    expect(mockPush).toHaveBeenLastCalledWith(`/organizations/${orgOne.id}/edit`);
  });
});
