import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react-native';

import { AuthProvider } from '@/auth/AuthContext';
import { OrganizationProvider } from '@/features/organization/OrganizationContext';

import { ProfileContent } from '../features/person/ProfileContent';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

jest.mock('expo-router', () => ({
  useRouter: () => ({ push: jest.fn() }),
}));

/**
 * StageArt Blueprint再構成 Phase 1e: 未認証アクセス制御. No stored Refresh
 * Token (unlike every other test in this suite's own SecureStore mock) -
 * AuthContext resolves straight to `status: 'unauthenticated'` at boot,
 * so every data hook here stays `enabled: false` (same
 * `status === 'authenticated'` gate every other feature hook in this app
 * already uses). Matches this app's existing precedent for every other
 * screen: there is no screen-level redirect-to-login guard anywhere in
 * this codebase's individual content components today (the real access
 * boundary is each Backend endpoint's own 401, plus - since Phase 1a -
 * the `(app)/_layout.tsx` route-group gate that keeps an unauthenticated
 * visitor from ever reaching this content in the real app); this only
 * asserts the same thing every other screen's own content component
 * already guarantees - no crash, no fabricated data, nothing
 * mutation-capable actually fires.
 */
describe('Profile: 未認証アクセス', () => {
  it('renders without crashing and without any authenticated Person data when no session is stored', async () => {
    global.fetch = jest.fn(async () => {
      throw new Error('No request should be made while unauthenticated - every hook here is status-gated.');
    });

    const queryClient = new QueryClient();
    render(
      <QueryClientProvider client={queryClient}>
        <AuthProvider>
          <OrganizationProvider>
            <ProfileContent />
          </OrganizationProvider>
        </AuthProvider>
      </QueryClientProvider>
    );

    await waitFor(() => expect(screen.getByTestId('profile-display-name')).toBeVisible());
    expect(screen.getByTestId('profile-display-name').props.children).toBe('プロフィール');
    expect(screen.queryByTestId('profile-person-id')).toBeNull();
    expect(screen.queryByTestId('profile-organization-org-1')).toBeNull();
  });
});
