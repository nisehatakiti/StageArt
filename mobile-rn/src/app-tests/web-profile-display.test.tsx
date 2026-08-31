import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react-native';

import { AuthProvider } from '@/auth/AuthContext';
import { OrganizationProvider } from '@/features/organization/OrganizationContext';

import { mockFetchRoutes, myDashboardEmpty, orgOne } from './__fixtures__/homeFixtures';
import { WebProfileContent } from '../components/web/WebProfileContent';

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
 * StageArt Web版 プロフィール Phase: rendered directly (not via
 * renderRouter(), and not by forcing `Platform.OS = 'web'` on
 * profile.tsx's own branch) - forcing Platform.OS to 'web' while still
 * using jest-expo's native-style test renderer was found to crash
 * expo-router's Stack frame-size logic
 * (`useFrameSize.tsx`'s `getBoundingClientRect`, which only exists on a
 * real DOM element react-native-web provides, never react-test-renderer's
 * native host tree) - a test-environment mismatch, not a defect in this
 * screen. WebLayout/WebProfileContent themselves never branch on
 * Platform.OS internally, so a direct render() (this file's own
 * proven-safe pattern, already used for DashboardScreen/
 * OrganizationEditScreen) exercises the exact same component tree
 * without touching react-navigation's Stack chrome at all. The
 * `/profile` Platform.OS branch itself is instead confirmed in a real
 * browser via Playwright (see this Phase's report), where
 * `Platform.OS === 'web'` is genuinely true.
 */
function renderProfile() {
  mockFetchRoutes([
    { test: (u) => u.endsWith('/organizations'), status: 200, body: [orgOne] },
    { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    { test: (u) => u.includes('/me/push-preference'), status: 200, body: { enabled: true, updated_at: '2026-08-18T00:00:00+09:00' } },
  ]);

  const queryClient = new QueryClient();
  return render(
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <OrganizationProvider>
          <WebProfileContent />
        </OrganizationProvider>
      </AuthProvider>
    </QueryClientProvider>
  );
}

describe('Web プロフィール: 表示', () => {
  it('shows basic info, organizations, and participating productions inside WebLayout', async () => {
    renderProfile();

    await waitFor(() => expect(screen.getByTestId('web-profile-display-name')).toBeVisible());
    // WebLayout's own header also shows the current Person's name (see
    // WebLayout.tsx's web-header-user) - scope to the page title itself
    // to avoid an ambiguous match.
    expect(screen.getByTestId('web-profile-display-name').props.children).toBe('舞台 芸術');
    expect(screen.getByTestId('web-sidebar')).toBeVisible();
    expect(screen.getByTestId('web-breadcrumb')).toBeVisible();

    await waitFor(() => expect(screen.getByTestId('web-profile-organization-org-1')).toBeVisible());
    expect(screen.getByText('○○演劇団')).toBeVisible();

    expect(screen.getByTestId('web-profile-productions-empty')).toBeVisible();
    expect(screen.getByTestId('web-profile-security')).toBeVisible();
  });
});
