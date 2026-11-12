import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

import { AuthProvider } from '@/auth/AuthContext';
import { OrganizationProvider } from '@/features/organization/OrganizationContext';

import { mockFetchRoutes, orgOne } from './__fixtures__/homeFixtures';
import OrganizationMembersOwnerTransferScreen from '../app/(app)/organizations/[id]/members/owner-transfer';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

const mockReplace = jest.fn();

/**
 * StageArt 団体管理UI整理 "代表者交代": select -> inline confirm -> the
 * real POST /organizations/{id}/owner-transfer (OwnerTransferUseCase).
 * Per the confirmed spec ("処理成功後はOrganization ContextではなくHome
 * 画面へ移動する"), success navigates to /dashboard, not back into this
 * same Organization's own management screens.
 *
 * Rendered directly (not via renderRouter()) for the same reason as
 * web-organization-edit-save.test.tsx and web-organization-members-add.test.tsx:
 * renderRouter()'s background drawer-menu query refetch intermittently
 * collides with this screen's own press-driven state update
 * ("overlapping act() calls") and silently drops it in this test
 * environment - confirmed flaky (passed once, failed once) under
 * renderRouter() despite the screen's own state-update logic being a
 * plain synchronous setState with no real bug.
 */
jest.mock('expo-router', () => ({
  useRouter: () => ({ push: jest.fn(), replace: mockReplace }),
  useLocalSearchParams: () => ({ id: 'org-1' }),
}));

function renderOwnerTransfer() {
  mockFetchRoutes([
    { test: (u) => u.endsWith('/organizations/org-1'), status: 200, body: orgOne },
    { test: (u) => u.endsWith('/organizations'), status: 200, body: [orgOne] },
    {
      test: (u) => u.endsWith(`/organizations/${orgOne.id}/members`),
      status: 200,
      body: [
        { membership_id: 'm-1', person_id: 'person-1', person_family_name: '舞台', person_given_name: '芸術', role_key: 'OWNER', joined_at: '2026-01-01T00:00:00+09:00' },
        { membership_id: 'm-2', person_id: 'person-2', person_family_name: '山田', person_given_name: '太郎', role_key: 'MEMBER', joined_at: '2026-01-02T00:00:00+09:00' },
      ],
    },
    { test: (u) => u.endsWith(`/organizations/${orgOne.id}/owner-transfer`), status: 204, body: null },
  ]);

  const queryClient = new QueryClient();
  return render(
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <OrganizationProvider>
          <OrganizationMembersOwnerTransferScreen />
        </OrganizationProvider>
      </AuthProvider>
    </QueryClientProvider>
  );
}

describe('Web 団体管理: メンバー管理 > 代表者交代', () => {
  beforeEach(() => {
    mockReplace.mockClear();
  });

  it('selects a candidate, confirms, and calls the real owner-transfer endpoint', async () => {
    renderOwnerTransfer();

    await waitFor(() => expect(screen.getByTestId('organization-owner-transfer-candidate-person-2')).toBeVisible());

    fireEvent.press(screen.getByTestId('organization-owner-transfer-candidate-person-2'));

    await waitFor(() => expect(screen.getByTestId('organization-owner-transfer-confirm')).toBeVisible());
    expect(screen.getByTestId('organization-owner-transfer-confirm')).toHaveTextContent(/山田/);

    fireEvent.press(screen.getByTestId('organization-owner-transfer-confirm-submit'));

    await waitFor(() => {
      const postCall = (global.fetch as jest.Mock).mock.calls.find(
        ([url, options]: [string, RequestInit]) => url.includes(`/organizations/${orgOne.id}/owner-transfer`) && options?.method === 'POST'
      );
      expect(postCall).toBeDefined();
      expect(JSON.parse(postCall[1].body as string)).toMatchObject({ new_owner_person_id: 'person-2' });
    });

    await waitFor(() => expect(mockReplace).toHaveBeenCalledWith('/dashboard'));
  });
});
