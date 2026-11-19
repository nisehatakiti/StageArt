import { fireEvent, screen, waitFor } from '@testing-library/react-native';

import { orgOne } from './__fixtures__/homeFixtures';
import { renderSidebarAs, shownOrganizationMenuItems } from './__fixtures__/sidebarRoleHarness';

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
 * A delegate (ORGANIZATION_MANAGER) given only the メンバー管理 area sees
 * 団体情報 (tied to the Role) and メンバー管理 - not 代表者交代 (Owner only),
 * nor 公演・活動一覧 / 会計管理 (areas they were not given).
 */
describe('Web sidebar: Organization management items for a delegate', () => {
  it('shows 団体情報 and メンバー管理 only for the メンバー管理 area, and both open', async () => {
    renderSidebarAs([{ ...orgOne, current_person_role: 'ORGANIZATION_MANAGER', current_person_management_areas: ['MEMBERS'], accounting_enabled: true }]);

    await waitFor(() => expect(screen.getByTestId('web-chrome-nav-organization-info')).toBeVisible());
    expect(shownOrganizationMenuItems()).toEqual(['organization-info', 'organization-members']);
    expect(screen.queryByText('代表者交代')).toBeNull();
    expect(screen.queryByText('会計管理')).toBeNull();
    expect(screen.queryByText('公演・活動一覧')).toBeNull();
    fireEvent.press(screen.getByTestId('web-chrome-nav-organization-info'));
    expect(mockPush).toHaveBeenLastCalledWith(`/organizations/${orgOne.id}/edit`);
    fireEvent.press(screen.getByTestId('web-chrome-nav-organization-members'));
    expect(mockPush).toHaveBeenLastCalledWith(`/organizations/${orgOne.id}/members`);
  });
});
