import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react-native';

import { AuthProvider } from '@/auth/AuthContext';
import { OrganizationProvider } from '@/features/organization/OrganizationContext';

import { mockFetchRoutes, myDashboardEmpty, orgOne } from './__fixtures__/homeFixtures';
import { ProfileContent } from '../features/person/ProfileContent';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

jest.mock('expo-router', () => ({
  useRouter: () => ({ push: jest.fn() }),
}));

/**
 * StageArt Blueprint再構成 Phase 1e: renders the current `ProfileContent`
 * (Person information only, since Phase 1d - security/account content
 * moved to AccountContent) directly, not via renderRouter() - see this
 * file's own previous docblock (kept below) for why a direct render() is
 * used at all.
 *
 * Forcing `Platform.OS = 'web'` while still using jest-expo's native-style
 * test renderer was found to crash expo-router's Stack frame-size logic
 * (`useFrameSize.tsx`'s `getBoundingClientRect`, which only exists on a
 * real DOM element react-native-web provides, never react-test-renderer's
 * native host tree) - a test-environment mismatch, not a defect in this
 * screen. ProfileContent never branches on Platform.OS internally (it is
 * the single Web/Native-shared implementation now), so a direct render()
 * exercises the exact same component tree without touching
 * react-navigation's Stack chrome at all.
 */
function renderProfile() {
  mockFetchRoutes([
    { test: (u) => u.endsWith('/organizations'), status: 200, body: [orgOne] },
    { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
  ]);

  const queryClient = new QueryClient();
  return render(
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <OrganizationProvider>
          <ProfileContent />
        </OrganizationProvider>
      </AuthProvider>
    </QueryClientProvider>
  );
}

describe('Profile: 表示', () => {
  it('shows basic info, organizations, and participating productions - no Account/security content', async () => {
    renderProfile();

    await waitFor(() => expect(screen.getByTestId('profile-display-name')).toBeVisible());
    expect(screen.getByTestId('profile-display-name').props.children).toBe('舞台 芸術');

    await waitFor(() => expect(screen.getByTestId(`profile-organization-${orgOne.id}`)).toBeVisible());
    expect(screen.getByText('○○演劇団')).toBeVisible();

    expect(screen.getByTestId('profile-productions-empty')).toBeVisible();

    // StageArt Blueprint再構成 Phase 1d/1e: Account/security content must
    // never appear on Profile - it lives on /account (AccountContent) now.
    expect(screen.queryByTestId('account-security-section')).toBeNull();
    expect(screen.queryByText('パスワードを変更')).toBeNull();
    expect(screen.queryByText('ログアウト')).toBeNull();
  });
});
