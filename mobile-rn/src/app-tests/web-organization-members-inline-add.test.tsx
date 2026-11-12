import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

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

function renderMembers() {
  mockFetchRoutes([
    { test: (u) => u.endsWith('/organizations/org-1'), status: 200, body: orgOne },
    { test: (u) => u.endsWith('/organizations'), status: 200, body: [orgOne] },
    { test: (u) => u.endsWith('/organizations/org-1/members'), status: 200, body: [] },
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

describe('Web メンバー管理: インライン追加', () => {
  it('adds a member inline from the bottom of the table', async () => {
    renderMembers();

    await waitFor(() => expect(screen.getByTestId('organization-members-add-member-form')).toBeVisible());

    fireEvent.changeText(screen.getByTestId('organization-members-new-member-name'), '新規メンバー');
    fireEvent.changeText(screen.getByTestId('organization-members-new-member-email'), 'newcomer@example.com');
    fireEvent.changeText(screen.getByTestId('organization-members-new-member-remarks'), '初回公演から参加予定');
    await waitFor(() => expect(screen.getByTestId('organization-members-new-member-email').props.value).toBe('newcomer@example.com'));

    (global.fetch as jest.Mock).mockImplementationOnce(async () => ({
      ok: true,
      status: 201,
      text: async () =>
        JSON.stringify({
          outcome: 'INVITATION_CREATED',
          member: null,
          invitation: {
            id: 'inv-1',
            organization_id: 'org-1',
            email: 'newcomer@example.com',
            name: '新規メンバー',
            invited_by_person_id: 'person-1',
            remarks: '初回公演から参加予定',
            status: 'PENDING',
            created_at: '2026-01-01T00:00:00+09:00',
            expires_at: '2026-01-02T00:00:00+09:00',
            consumed_at: null,
            is_expired: false,
          },
        }),
      json: async () => ({}),
    }));

    fireEvent.press(screen.getByTestId('organization-members-add-member'));

    await waitFor(() => {
      const postCall = (global.fetch as jest.Mock).mock.calls.find(
        ([url, options]: [string, RequestInit]) => url.includes('/organizations/org-1/invitations') && options?.method === 'POST'
      );
      expect(postCall).toBeDefined();
      const body = JSON.parse(postCall![1].body as string);
      expect(body).toMatchObject({ name: '新規メンバー', email: 'newcomer@example.com', remarks: '初回公演から参加予定' });
    });

    await waitFor(() => expect(screen.getByTestId('organization-members-add-result')).toBeVisible());
  });
});
