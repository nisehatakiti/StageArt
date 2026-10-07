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
 * StageArt Organization管理UI/UX統一 "メンバー管理": Productionのメンバー
 * 管理画面を基準にしたテーブル構成 - 「あなたの役割」カードは廃止し、名前/
 * 役割/メールアドレス/備考/代理人/操作の列を持つ1つのテーブルで表示する。
 *
 * Rendered directly (not via renderRouter()) for the same reason as
 * web-organization-edit-save.test.tsx and web-organization-members-add.test.tsx:
 * this screen's remarks column is populated via a useEffect driven by
 * the members query result, and renderRouter()'s forced fake timers
 * drop that follow-on state update in this test environment even with
 * an explicit waitFor (confirmed: it never resolves, not just slow) -
 * real app behavior is unaffected, matching those two files' own
 * documented precedent for this environment limitation.
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

describe('Web メンバー管理', () => {
  it('shows the real member roster as a table with role/email/remarks columns', async () => {
    renderMembers();

    await waitFor(() => expect(screen.getByTestId('organization-members-list')).toBeVisible());
    expect(screen.getByTestId('organization-member-person-2')).toHaveTextContent(/山田/);
    expect(screen.getByTestId('organization-member-email-person-2')).toHaveTextContent(/yamada@example.com/);
    await waitFor(() => expect(screen.getByTestId('organization-member-remarks-person-2').props.value).toBe('衣装合わせ待ち'));
    expect(screen.getByTestId('organization-members-add')).toBeVisible();
    expect(screen.getByTestId('organization-members-delegate')).toBeVisible();
    expect(screen.getByTestId('organization-members-owner-transfer')).toBeVisible();
  });
});
