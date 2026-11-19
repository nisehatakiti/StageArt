import { screen, waitFor } from '@testing-library/react-native';

import { orgOne } from './__fixtures__/homeFixtures';
import { fetchCalls, renderMembersAs } from './__fixtures__/membersScreenHarness';

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
 * A delegate who was not given メンバー管理 (here: 公演管理 and 会計管理
 * only) opening the URL directly sees no roster, and the roster and
 * invitations are never requested.
 */
describe('Web メンバー管理: メンバー管理を任されていない代理人', () => {
  it('is refused and nothing is requested', async () => {
    renderMembersAs({ ...orgOne, current_person_role: 'ORGANIZATION_MANAGER', current_person_management_areas: ['PRODUCTIONS', 'ACCOUNTING'] });

    await waitFor(() => expect(screen.getByTestId('organization-members-list-unavailable')).toBeVisible());
    expect(screen.queryByTestId('organization-members-list')).toBeNull();
    expect(fetchCalls().some(([url]) => /\/organizations\/org-1\/(members|invitations|delegates)$/.test(url))).toBe(false);
  });
});
