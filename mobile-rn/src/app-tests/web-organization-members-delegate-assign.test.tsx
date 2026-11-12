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
    {
      test: (u) => u.endsWith('/organizations/org-1/members'),
      status: 200,
      body: [
        { membership_id: 'm-1', person_id: 'person-1', person_family_name: '舞台', person_given_name: '芸術', email: 'owner@example.com', role_key: 'OWNER', joined_at: '2026-01-01T00:00:00+09:00', remarks: null },
        { membership_id: 'm-2', person_id: 'person-2', person_family_name: '山田', person_given_name: '太郎', email: 'yamada@example.com', role_key: 'MEMBER', joined_at: '2026-01-02T00:00:00+09:00', remarks: null },
      ],
    },
    { test: (u) => u.endsWith('/organizations/org-1/invitations'), status: 200, body: [] },
    { test: (u) => u.endsWith('/organizations/org-1/delegates'), status: 204, body: null },
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

describe('Web メンバー管理: 代理人の設定', () => {
  it('lets the Owner assign a plain member as delegate via the real endpoint', async () => {
    renderMembers();

    await waitFor(() => expect(screen.getByTestId('organization-member-delegate-person-2')).toBeVisible());

    fireEvent.press(screen.getByTestId('organization-member-delegate-person-2'));

    await waitFor(() => {
      const postCall = (global.fetch as jest.Mock).mock.calls.find(
        ([url, options]: [string, RequestInit]) => url.includes('/organizations/org-1/delegates') && !url.includes('/person-') && options?.method === 'POST'
      );
      expect(postCall).toBeDefined();
      expect(JSON.parse(postCall![1].body as string)).toMatchObject({ person_id: 'person-2' });
    });
  });
});
