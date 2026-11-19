import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react-native';

import { AuthProvider } from '@/auth/AuthContext';

import { mockFetchRoutes, orgOne } from './__fixtures__/homeFixtures';
import OrganizationOwnerTransferScreen from '../app/(app)/organizations/[id]/members/owner-transfer';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

jest.mock('expo-router', () => ({
  useRouter: () => ({ push: jest.fn(), replace: jest.fn(), back: jest.fn() }),
  useLocalSearchParams: () => ({ id: 'org-1' }),
}));

/**
 * 代表者交代 is the Owner's alone (OwnerTransferUseCase: OWNER only). A
 * delegate no longer sees it in the menu, and opening the URL directly is
 * still refused by the screen without loading the roster.
 */
describe('Web 代表者交代: a delegate opening the URL directly', () => {
  it('is refused and the roster is not requested', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [{ ...orgOne, current_person_role: 'ORGANIZATION_MANAGER' }] },
      { test: (u) => u.endsWith('/organizations/org-1/members'), status: 200, body: [] },
    ]);

    const queryClient = new QueryClient();
    render(
      <QueryClientProvider client={queryClient}>
        <AuthProvider>
          <OrganizationOwnerTransferScreen />
        </AuthProvider>
      </QueryClientProvider>
    );

    await waitFor(() => expect(screen.getByTestId('organization-owner-transfer-forbidden')).toBeVisible());
    const urls = (global.fetch as jest.Mock).mock.calls.map(([url]) => String(url));
    expect(urls.some((url) => url.endsWith('/organizations/org-1/members'))).toBe(false);
  });
});
