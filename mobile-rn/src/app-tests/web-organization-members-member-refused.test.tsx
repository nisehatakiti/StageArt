import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react-native';

import { AuthProvider } from '@/auth/AuthContext';
import { OrganizationProvider } from '@/features/organization/OrganizationContext';

import { mockFetchRoutes, orgTwo } from './__fixtures__/homeFixtures';
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
  useLocalSearchParams: () => ({ id: 'org-2' }),
}));

/**
 * メンバー管理 is for the Owner and the delegate (ListOrganizationMembersUseCase).
 * A plain member no longer sees it in the menu; opening the URL directly
 * still shows no roster and never asks the API for it.
 */
describe('Web メンバー管理: a member opening the URL directly', () => {
  it('sees no roster and the roster is not requested', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations/org-2'), status: 200, body: orgTwo },
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [orgTwo] },
      { test: (u) => u.endsWith('/organizations/org-2/members'), status: 200, body: [] },
      { test: (u) => u.endsWith('/organizations/org-2/invitations'), status: 200, body: [] },
    ]);

    const queryClient = new QueryClient();
    render(
      <QueryClientProvider client={queryClient}>
        <AuthProvider>
          <OrganizationProvider>
            <OrganizationMembersScreen />
          </OrganizationProvider>
        </AuthProvider>
      </QueryClientProvider>
    );

    await waitFor(() => expect(screen.getByTestId('organization-members-list-unavailable')).toBeVisible());
    expect(screen.queryByTestId('organization-members-list')).toBeNull();
    const urls = (global.fetch as jest.Mock).mock.calls.map(([url]) => String(url));
    expect(urls.some((url) => /\/organizations\/org-2\/(members|invitations)$/.test(url))).toBe(false);
  });
});
