import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react-native';

import { AuthProvider } from '@/auth/AuthContext';
import { OrganizationProvider } from '@/features/organization/OrganizationContext';

import { mockFetchRoutes, orgOne } from './__fixtures__/homeFixtures';
import OrganizationMembersScreen from '../app/(app)/organizations/[id]/members';

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
 * StageArt Organization管理UI/UX統一 "メンバー管理": split per this
 * codebase's established one-it()-per-file convention for this kind of
 * query+effect-driven screen test (confirmed empirically this round:
 * multiple it()s in one file for this exact screen produced
 * "Unable to find an element" failures that vanished when each test ran
 * alone - see git history/session notes for the investigation; this is
 * the same class of environment limitation already documented for
 * renderRouter()-based tests, just also reachable via direct render()
 * here).
 */
function renderMembers() {
  mockFetchRoutes([
    { test: (u) => u.endsWith('/organizations/org-1'), status: 200, body: orgOne },
    { test: (u) => u.endsWith('/organizations'), status: 200, body: [orgOne] },
    {
      test: (u) => u.endsWith('/organizations/org-1/members'),
      status: 200,
      body: [
        { membership_id: 'm-1', person_id: 'person-1', person_family_name: '舞台', person_given_name: '芸術', email: 'owner@example.com', role_key: 'OWNER', joined_at: '2026-01-01T00:00:00+09:00', remarks: null },
        { membership_id: 'm-2', person_id: 'person-2', person_family_name: '山田', person_given_name: '太郎', email: 'yamada@example.com', role_key: 'MEMBER', joined_at: '2026-01-02T00:00:00+09:00', remarks: '衣装合わせ待ち' },
        { membership_id: 'm-3', person_id: 'person-3', person_family_name: '佐藤', person_given_name: '花子', email: 'sato@example.com', role_key: 'ORGANIZATION_MANAGER', joined_at: '2026-01-03T00:00:00+09:00', remarks: null },
      ],
    },
    { test: (u) => u.endsWith('/organizations/org-1/invitations'), status: 200, body: [] },
  ]);

  const queryClient = new QueryClient();
  return render(
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <OrganizationProvider>
          <OrganizationMembersScreen />
        </OrganizationProvider>
      </AuthProvider>
    </QueryClientProvider>
  );
}

describe('Web メンバー管理: 一覧テーブル', () => {
  it('shows the real member roster as a table with name/role/remarks/email columns, no 追加/代理人設定 buttons', async () => {
    renderMembers();

    await waitFor(() => expect(screen.getByTestId('organization-members-list')).toBeVisible());
    expect(screen.getByTestId('organization-member-person-2')).toHaveTextContent(/山田/);
    expect(screen.getByTestId('organization-member-person-2')).toHaveTextContent(/yamada@example\.com/);
    await waitFor(() => expect(screen.getByTestId('organization-member-remarks-person-2').props.value).toBe('衣装合わせ待ち'));

    // §2 confirmed spec: メンバーを追加/代理人を設定 are no longer independent
    // buttons - only 代表者交代/参加コード remain in the actions row.
    expect(screen.queryByTestId('organization-members-add')).toBeNull();
    expect(screen.queryByTestId('organization-members-delegate')).toBeNull();
    expect(screen.getByTestId('organization-members-owner-transfer')).toBeVisible();
    expect(screen.getByTestId('organization-members-invite')).toBeVisible();
  });
});
