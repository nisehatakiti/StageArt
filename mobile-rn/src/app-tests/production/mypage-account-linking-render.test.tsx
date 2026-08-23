import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { currentPerson, mockFetchRoutes, productionOne, pushPreferenceOn } from './__fixtures__/productionShellFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * §"アカウント連携" / §12 of this Phase's instruction: the Google-link,
 * Email+Password-link, password-change, and email-verification-resend
 * actions are always offered (no "already linked" indicator - GET /me
 * exposes no such status; see useAccountLinking.ts's docblock for why),
 * and none of their labels ever use an Infrastructure/WordPress term.
 * A pure render check - the actual linking behavior is covered directly
 * against the underlying functions in src/features/auth/api.test.ts (see
 * that file's docblock for why the interaction path itself is not
 * exercised here).
 */
describe('My Page: account linking & security section', () => {
  it('shows Google link, Email+Password link, change-password, and resend-verification actions with no WordPress terms', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne },
      { test: (u) => u.endsWith('/me'), status: 200, body: currentPerson },
      { test: (u) => u.includes('/me/push-preference'), status: 200, body: pushPreferenceOn },
    ]);

    renderRouter('src/app', { initialUrl: '/production/prod-1/mypage' });

    await waitFor(() => expect(screen.getByTestId('mypage-link-google-button')).toBeVisible());
    expect(screen.getByText('Googleアカウントを連携')).toBeVisible();
    expect(screen.getByTestId('mypage-link-email-toggle')).toBeVisible();
    expect(screen.getByText('メールアドレス＋パスワードを追加')).toBeVisible();
    expect(screen.getByTestId('mypage-change-password-toggle')).toBeVisible();
    expect(screen.getByTestId('mypage-resend-verification-button')).toBeVisible();

    expect(screen.queryByText(/WordPress/i)).toBeNull();
    expect(screen.queryByText(/Application Password/i)).toBeNull();
  });
});
