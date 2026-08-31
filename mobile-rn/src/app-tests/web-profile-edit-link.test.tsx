import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

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

const mockPush = jest.fn();

jest.mock('expo-router', () => ({
  useRouter: () => ({ push: mockPush }),
}));

/**
 * StageArt Web版 プロフィール Phase: 「プロフィール → プロフィールを編集 →
 * 姓名変更 → 保存 → プロフィールへ戻る」の最初の一歩 - reuses set-name.tsx
 * unchanged (see WebProfileContent's own docblock: that screen was
 * already platform-agnostic and already had this exact
 * `return_to=/profile` pattern from MyPageContent.tsx, so this only
 * needs to link there with the same hints/return_to shape).
 */
describe('Web プロフィール: プロフィールを編集への導線', () => {
  it('navigates to set-name with the current name as hints and return_to=/profile', async () => {
    mockFetchRoutes([
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

    await waitFor(() => expect(screen.getByTestId('web-profile-edit-link')).toBeVisible());
    fireEvent.press(screen.getByTestId('web-profile-edit-link'));

    expect(mockPush).toHaveBeenCalledWith({
      pathname: '/set-name',
      params: { family_name_hint: '舞台', given_name_hint: '芸術', return_to: '/profile' },
    });
  });
});
