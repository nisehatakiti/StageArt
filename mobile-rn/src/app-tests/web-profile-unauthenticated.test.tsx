import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react-native';

import { AuthProvider } from '@/auth/AuthContext';
import { OrganizationProvider } from '@/features/organization/OrganizationContext';

import { WebProfileContent } from '../components/web/WebProfileContent';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

jest.mock('expo-router', () => ({
  useRouter: () => ({ push: jest.fn() }),
}));

/**
 * StageArt Web版 プロフィール Phase: 未認証アクセス制御. No stored Refresh
 * Token (unlike every other test in this suite's own SecureStore mock) -
 * AuthContext resolves straight to `status: 'unauthenticated'` at boot,
 * so every data hook here stays `enabled: false` (same
 * `status === 'authenticated'` gate every other feature hook in this
 * app already uses). Matches this app's existing precedent for every
 * other WebLayout screen (dashboard.tsx, organizations/*,
 * productions/*): there is no screen-level redirect-to-login guard
 * anywhere in this codebase today (the real access boundary is each
 * Backend endpoint's own 401), so this only asserts the same thing those
 * screens already guarantee - no crash, no fabricated data, nothing
 * mutation-capable actually fires.
 */
describe('Web プロフィール: 未認証アクセス', () => {
  it('renders without crashing and without any authenticated Person data when no session is stored', async () => {
    global.fetch = jest.fn(async () => {
      throw new Error('No request should be made while unauthenticated - every hook here is status-gated.');
    });

    const queryClient = new QueryClient();
    render(
      <QueryClientProvider client={queryClient}>
        <AuthProvider>
          <OrganizationProvider>
            <WebProfileContent />
          </OrganizationProvider>
        </AuthProvider>
      </QueryClientProvider>
    );

    await waitFor(() => expect(screen.getByTestId('web-profile-display-name')).toBeVisible());
    expect(screen.getByTestId('web-profile-display-name').props.children).toBe('マイページ');
    expect(screen.queryByTestId('web-profile-person-id')).toBeNull();
    expect(screen.queryByTestId('web-profile-organization-org-1')).toBeNull();
  });
});
