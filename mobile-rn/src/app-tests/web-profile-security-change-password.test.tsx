import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

import { AuthProvider } from '@/auth/AuthContext';

import { mockFetchRoutes } from './__fixtures__/homeFixtures';
import { AccountContent } from '../features/account/AccountContent';

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
 * StageArt Blueprint再構成 Phase 1e: パスワード変更 - now lives on
 * `/account` (AccountContent), moved out of the old
 * WebProfileContent/MyPageContent combined screen by Phase 1d. Reuses
 * the exact same useChangePassword() hook
 * (features/mypage/useAccountLinking.ts, unchanged).
 */
describe('Account: パスワード変更', () => {
  it('calls the real change-password endpoint and shows success feedback', async () => {
    mockFetchRoutes([
      { test: (u) => u.includes('/me/push-preference'), status: 200, body: { enabled: true, updated_at: '2026-08-18T00:00:00+09:00' } },
      { test: (u) => u.endsWith('/user-accounts/email-credential/password'), status: 200, body: { success: true } },
    ]);

    const queryClient = new QueryClient();
    render(
      <QueryClientProvider client={queryClient}>
        <AuthProvider>
          <AccountContent />
        </AuthProvider>
      </QueryClientProvider>
    );

    await waitFor(() => expect(screen.getByTestId('account-change-password-toggle')).toBeVisible());
    fireEvent.press(screen.getByTestId('account-change-password-toggle'));
    await waitFor(() => expect(screen.getByTestId('account-current-password')).toBeVisible());

    fireEvent.changeText(screen.getByTestId('account-current-password'), 'current-pass-123');
    fireEvent.changeText(screen.getByTestId('account-new-password'), 'new-pass-456');
    await waitFor(() => expect(screen.getByTestId('account-new-password').props.value).toBe('new-pass-456'));

    fireEvent.press(screen.getByTestId('account-change-password-submit'));

    await waitFor(() => expect(screen.getByTestId('account-change-password-feedback')).toBeVisible());
    expect(screen.getByText('パスワードを変更しました。')).toBeVisible();

    const putCall = (global.fetch as jest.Mock).mock.calls.find(([url]: [string]) => url.endsWith('/user-accounts/email-credential/password'));
    expect(putCall).toBeDefined();
    const sentBody = JSON.parse(putCall[1].body as string);
    expect(sentBody).toMatchObject({ current_password: 'current-pass-123', new_password: 'new-pass-456' });
  });
});
