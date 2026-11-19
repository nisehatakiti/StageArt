import { screen, waitFor } from '@testing-library/react-native';

import { orgOne } from './__fixtures__/homeFixtures';
import { AREAS, fetchCalls, renderMembersAs } from './__fixtures__/membersScreenHarness';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

jest.mock('expo-router', () => ({
  useRouter: () => ({ push: jest.fn(), replace: jest.fn() }),
  useLocalSearchParams: () => ({ id: 'org-1' }),
}));

/**
 * A delegate holding every area (メンバー管理 included) can use the roster
 * and, through メンバー管理, the 参加コード button; but 代理人を設定 /
 * 管理領域 / 代表者交代 stay the Owner's: none of those controls is shown,
 * and the delegate-areas list is never requested - so a delegate has no
 * way to widen their own areas here.
 */
describe('Web メンバー管理: 代理人本人の画面', () => {
  it('shows the roster without any delegate or area control', async () => {
    renderMembersAs({ ...orgOne, current_person_role: 'ORGANIZATION_MANAGER', current_person_management_areas: [...AREAS] }, [
      { test: (u) => u.endsWith('/organizations/org-1/delegates'), status: 200, body: [{ person_id: 'person-3', areas: [...AREAS] }] },
    ]);

    await waitFor(() => expect(screen.getByTestId('organization-member-person-3')).toBeVisible());
    ['person-1', 'person-2', 'person-3'].forEach((person) => {
      expect(screen.queryByTestId(`organization-member-delegate-${person}`)).toBeNull();
      AREAS.forEach((area) => expect(screen.queryByTestId(`organization-member-area-${area}-${person}`)).toBeNull());
    });
    expect(screen.queryByText('公演管理')).toBeNull();
    expect(screen.getByTestId('organization-members-invite')).toBeVisible();
    expect(screen.queryByTestId('organization-members-owner-transfer')).toBeNull();
    expect(fetchCalls().some(([url]) => url.endsWith('/organizations/org-1/delegates'))).toBe(false);
  });
});
