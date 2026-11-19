import { screen, waitFor } from '@testing-library/react-native';

import { orgOne } from './__fixtures__/homeFixtures';
import { ORG_MENU_LABELS, renderSidebarAs, shownOrganizationMenuItems } from './__fixtures__/sidebarRoleHarness';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

jest.mock('expo-router', () => ({
  ...jest.requireActual('expo-router'),
  usePathname: () => '/organizations/org-1',
  useRouter: () => ({ push: jest.fn(), replace: jest.fn(), back: jest.fn() }),
}));

/**
 * A plain member gets no management item of the Organization - not even a
 * disabled one - and keeps the Fixed Area (ホーム, マイページ, 設定).
 */
describe('Web sidebar: Organization management items for a member', () => {
  it('shows none, and keeps the Fixed Area', async () => {
    renderSidebarAs([{ ...orgOne, current_person_role: 'MEMBER', accounting_enabled: true }]);

    await waitFor(() => expect(screen.getByTestId('web-chrome-context-label')).toHaveTextContent(orgOne.name));
    expect(shownOrganizationMenuItems()).toEqual([]);
    ORG_MENU_LABELS.forEach((label) => expect(screen.queryByText(label)).toBeNull());
    expect(screen.getByTestId('web-chrome-nav-home')).toBeVisible();
    expect(screen.getByTestId('web-chrome-nav-mypage')).toBeVisible();
    expect(screen.getByTestId('web-chrome-nav-settings')).toBeVisible();
    expect(screen.getByTestId('web-chrome-back-to')).toBeVisible();
  });
});
