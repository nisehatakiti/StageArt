import { screen, waitFor } from '@testing-library/react-native';

import { orgTwo } from './__fixtures__/homeFixtures';
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
  usePathname: () => '/organizations/org-1/edit',
  useRouter: () => ({ push: jest.fn(), replace: jest.fn(), back: jest.fn() }),
}));

/**
 * Someone outside org-1 (their only Organization is org-2) gets none of
 * org-1's management items.
 */
describe('Web sidebar: Organization management items for someone outside it', () => {
  it('shows none of them', async () => {
    renderSidebarAs([{ ...orgTwo, current_person_role: 'OWNER' }]);

    await waitFor(() => expect(screen.getByTestId('organizations-loaded')).toBeVisible());
    expect(screen.getByTestId('web-chrome-back-to')).toBeVisible();
    expect(screen.getByTestId('web-chrome-context-label')).toHaveTextContent(/^団体$/);
    expect(shownOrganizationMenuItems()).toEqual([]);
    ORG_MENU_LABELS.forEach((label) => expect(screen.queryByText(label)).toBeNull());
    expect(screen.getByTestId('web-chrome-nav-home')).toBeVisible();
  });
});
