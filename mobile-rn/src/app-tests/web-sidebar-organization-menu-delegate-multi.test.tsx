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
  usePathname: () => '/organizations/org-1/edit',
  useRouter: () => ({ push: mockPush, replace: jest.fn(), back: jest.fn() }),
}));

/**
 * A delegate given two areas (公演管理 and 会計管理) sees both of their
 * items next to 団体情報 - and still not メンバー管理 (not given) or
 * 代表者交代 (Owner only).
 */
describe('Web sidebar: Organization management items for a delegate holding several areas', () => {
  it('shows 公演・活動一覧 and 会計管理 next to 団体情報, and both open', async () => {
    renderSidebarAs([
      { ...orgOne, current_person_role: 'ORGANIZATION_MANAGER', current_person_management_areas: ['PRODUCTIONS', 'ACCOUNTING'], accounting_enabled: true },
    ]);

    await waitFor(() => expect(screen.getByTestId('web-chrome-nav-organization-info')).toBeVisible());
    expect(shownOrganizationMenuItems()).toEqual(['organization-info', 'organization-productions', 'organization-accounting']);
    expect(screen.queryByTestId('web-chrome-nav-organization-members')).toBeNull();
    expect(screen.queryByText('代表者交代')).toBeNull();
    fireEvent.press(screen.getByTestId('web-chrome-nav-organization-productions'));
    expect(mockPush).toHaveBeenLastCalledWith(`/organizations/${orgOne.id}/productions`);
    fireEvent.press(screen.getByTestId('web-chrome-nav-organization-accounting'));
    expect(mockPush).toHaveBeenLastCalledWith(`/organizations/${orgOne.id}/accounting`);
  });
});
