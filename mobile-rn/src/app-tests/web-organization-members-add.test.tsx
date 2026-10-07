import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

import { AuthProvider } from '@/auth/AuthContext';
import { OrganizationProvider } from '@/features/organization/OrganizationContext';

import { mockFetchRoutes } from './__fixtures__/homeFixtures';
import OrganizationMembersAddScreen from '../app/(app)/organizations/[id]/members/add';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

const mockPush = jest.fn();

/**
 * StageArt 団体管理UI整理 "メンバーを追加": POST /organizations/{id}/invitations
 * (CreateOrganizationInvitationUseCase) now backs this screen for real -
 * 氏名＋メールアドレス＋備考, mirroring the Production participant-add
 * flow's own shape.
 *
 * Rendered directly (not via renderRouter()) for the same reason as
 * web-organization-edit-save.test.tsx: renderRouter()'s background
 * drawer-menu query refetch collides with this screen's own
 * fireEvent.changeText state updates ("overlapping act() calls") and
 * silently drops them in this test environment - real app behavior is
 * unaffected.
 */
jest.mock('expo-router', () => ({
  useRouter: () => ({ push: mockPush, replace: jest.fn() }),
  useLocalSearchParams: () => ({ id: 'org-1' }),
}));

function renderAdd() {
  mockFetchRoutes([
    {
      test: (u) => u.endsWith('/organizations/org-1/invitations'),
      status: 201,
      body: {
        outcome: 'INVITATION_CREATED',
        member: null,
        invitation: {
          id: 'inv-1',
          organization_id: 'org-1',
          email: 'newcomer@example.com',
          name: '新規メンバー',
          invited_by_person_id: 'person-1',
          remarks: null,
          status: 'PENDING',
          created_at: '2026-01-01T00:00:00+09:00',
          expires_at: '2026-01-02T00:00:00+09:00',
          consumed_at: null,
        },
      },
    },
  ]);

  const queryClient = new QueryClient();
  return render(
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <OrganizationProvider>
          <OrganizationMembersAddScreen />
        </OrganizationProvider>
      </AuthProvider>
    </QueryClientProvider>
  );
}

describe('Web 団体管理: メンバーを追加', () => {
  it('submits 氏名＋メールアドレス and shows the real outcome message', async () => {
    renderAdd();

    await waitFor(() => expect(screen.getByTestId('organization-members-add-name')).toBeVisible());

    fireEvent.changeText(screen.getByTestId('organization-members-add-name'), '新規メンバー');
    fireEvent.changeText(screen.getByTestId('organization-members-add-email'), 'newcomer@example.com');
    await waitFor(() => expect(screen.getByTestId('organization-members-add-email').props.value).toBe('newcomer@example.com'));

    fireEvent.press(screen.getByTestId('organization-members-add-submit'));

    await waitFor(() => expect(screen.getByTestId('organization-members-add-result')).toBeVisible());
    expect(screen.getByTestId('organization-members-add-result')).toHaveTextContent(/招待メール/);

    const postCall = (global.fetch as jest.Mock).mock.calls.find(
      ([url, options]: [string, RequestInit]) => url.includes('/organizations/org-1/invitations') && options?.method === 'POST'
    );
    expect(postCall).toBeDefined();
    const sentBody = JSON.parse(postCall[1].body as string);
    expect(sentBody).toMatchObject({ name: '新規メンバー', email: 'newcomer@example.com' });
  });
});
