import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react-native';

import { AuthProvider } from '@/auth/AuthContext';
import { OrganizationProvider } from '@/features/organization/OrganizationContext';

import { mockFetchRoutes, myDashboardEmpty } from './__fixtures__/homeFixtures';
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
 * StageArt Web版 プロフィール Phase: 保存後にリロードしても表示が維持される
 * ことの確認。WebProfileContent keeps no locally-edited name in state at
 * all - it only ever reads useCurrentPerson()'s (GET /me) fetch result -
 * so a fresh mount against a GET that already reflects a previously-saved
 * name (exactly what a real reload after set-name.tsx's own save would
 * produce) is a faithful reload simulation, same reasoning already
 * applied to the Organization/Production edit screens' own reload tests.
 */
describe('Web プロフィール: 保存後にリロードしても新しい氏名が表示される', () => {
  it('shows the already-saved name from a fresh GET /me, not any stale default', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/me'), status: 200, body: { id: 'person-1', word_press_user_id: 1, email_verified: true, family_name: '劇団', given_name: '花子' } },
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
      { test: (u) => u.includes('/me/push-preference'), status: 200, body: { enabled: true, updated_at: '2026-08-18T00:00:00+09:00' } },
    ]);

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

    await waitFor(() => expect(screen.getByTestId('web-profile-display-name').props.children).toBe('劇団 花子'));
  });
});
