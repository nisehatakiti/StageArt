import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

import { AuthProvider } from '@/auth/AuthContext';
import { OrganizationProvider } from '@/features/organization/OrganizationContext';

import { mockFetchRoutes, orgOne } from './__fixtures__/homeFixtures';
import OrganizationEditScreen from '../app/(app)/organizations/[id]/edit';

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
 * StageArt SNS registration simplification: アカウントIDだけで登録でき、
 * profile URL入力欄はUIから廃止されている。X/Instagram/Facebookの公開
 * URLはBackend側で確実に自動生成される(OrganizationSNSUseCaseTest.php's
 * own coverage) - このFrontendテストは、UIにprofile_url入力欄が無いこと、
 * アカウントIDのみで送信できること、既存のprofile_url=null/OTHERデータが
 * 壊れずに表示されることを確認する。
 */
function renderEdit(sns: unknown[]) {
  mockFetchRoutes([
    { test: (u) => u.endsWith('/organizations/org-1'), status: 200, body: orgOne },
    { test: (u) => u.endsWith('/organizations'), status: 200, body: [orgOne] },
    { test: (u) => u.endsWith('/organizations/org-1/sns'), status: 200, body: sns },
    { test: (u) => u.endsWith('/organizations/org-1/external-links'), status: 200, body: [] },
  ]);

  const queryClient = new QueryClient();
  return render(
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <OrganizationProvider>
          <OrganizationEditScreen />
        </OrganizationProvider>
      </AuthProvider>
    </QueryClientProvider>
  );
}

describe('Web 団体情報編集: SNS (アカウントIDのみ登録)', () => {
  it('has no profile URL input and registers X with just the account identifier', async () => {
    renderEdit([]);

    await waitFor(() => expect(screen.getByTestId('organization-edit-sns-account-identifier')).toBeVisible());
    expect(screen.queryByTestId('organization-edit-sns-profile-url')).toBeNull();

    fireEvent.changeText(screen.getByTestId('organization-edit-sns-account-identifier'), 'stageart_theatre');
    await waitFor(() => expect(screen.getByTestId('organization-edit-sns-account-identifier').props.value).toBe('stageart_theatre'));

    fireEvent.press(screen.getByTestId('organization-edit-sns-add-submit'));

    await waitFor(() => {
      const postCall = (global.fetch as jest.Mock).mock.calls.find(
        ([url, init]: [string, RequestInit]) => url.endsWith('/organizations/org-1/sns') && init?.method === 'POST'
      );
      expect(postCall).toBeDefined();
      const body = JSON.parse(postCall![1].body as string);
      expect(body.account_identifier).toBe('stageart_theatre');
      expect(body.service).toBe('X');
      // The client derives the same URL Backend would anyway (Backend
      // is authoritative and overrides this for X/Instagram/Facebook -
      // see CreateOrganizationSNSUseCase::profileUrlForService()) - this
      // just confirms the client never sends a blank/garbage value.
      expect(body.profile_url).toBe('https://x.com/stageart_theatre');
    });
  });

  it('renders an existing SNS entry with a null profile_url without crashing', async () => {
    renderEdit([
      {
        id: 'sns-1',
        organization_id: 'org-1',
        service: 'OTHER',
        account_identifier: 'legacy_handle',
        profile_url: null,
        display_name: null,
        display_order: 0,
        visibility: true,
        created_at: '2026-01-01T00:00:00+09:00',
        updated_at: '2026-01-01T00:00:00+09:00',
      },
    ]);

    await waitFor(() => expect(screen.getByTestId('organization-sns-row-sns-1')).toBeVisible());
    expect(screen.getByTestId('organization-sns-row-sns-1')).toHaveTextContent(/legacy_handle/);
  });

  it('preserves an existing profile_url when editing a different field on the same SNS entry', async () => {
    renderEdit([
      {
        id: 'sns-2',
        organization_id: 'org-1',
        service: 'X',
        account_identifier: 'stageart_theatre',
        profile_url: 'https://x.com/stageart_theatre',
        display_name: null,
        display_order: 0,
        visibility: true,
        created_at: '2026-01-01T00:00:00+09:00',
        updated_at: '2026-01-01T00:00:00+09:00',
      },
    ]);

    await waitFor(() => expect(screen.getByTestId('organization-sns-visibility-sns-2')).toBeVisible());
    fireEvent.press(screen.getByTestId('organization-sns-visibility-sns-2'));

    await waitFor(() => {
      const putCall = (global.fetch as jest.Mock).mock.calls.find(
        ([url, init]: [string, RequestInit]) => url.endsWith('/organization-sns/sns-2') && init?.method === 'PUT'
      );
      expect(putCall).toBeDefined();
      const body = JSON.parse(putCall![1].body as string);
      expect(body.visibility).toBe(false);
      expect(body.profile_url).toBe('https://x.com/stageart_theatre');
    });
  });
});
