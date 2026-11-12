import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { currentPerson, mockFetchRoutes, pushPreferenceOn } from './__fixtures__/productionShellFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

// isGoogleSignInAvailable() would otherwise be false under Jest (the real
// module-status probe reports RNGoogleSignin "not found" here, same as a
// real Web deploy) - forced true so this render test can still assert on
// the Google-linking action's presence. See
// mypage-google-link-unavailable.test.tsx for the real, unmocked "hidden"
// case.
jest.mock('@/auth/googleSignIn', () => ({
  ...jest.requireActual('@/auth/googleSignIn'),
  isGoogleSignInAvailable: jest.fn(() => true),
}));

/**
 * §"アカウント連携" / §12 of this Phase's instruction, superseded by
 * StageArt Blueprint再構成 Phase 1d: these actions now live on `/account`
 * (features/account/AccountContent.tsx), not the Production Shell's
 * マイページ tab - see that Phase's report. The Google-link,
 * Email+Password-link, password-change, and email-verification-resend
 * actions are still always offered (no "already linked" indicator - GET
 * /me exposes no such status; see useAccountLinking.ts's docblock for
 * why), and none of their labels ever use an Infrastructure/WordPress
 * term. A pure render check - the actual linking behavior is covered
 * directly against the underlying functions in
 * src/features/auth/api.test.ts.
 */
describe('Account: linking & security section', () => {
  it('shows Google link, Email+Password link, change-password, and resend-verification actions with no WordPress terms', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/me'), status: 200, body: currentPerson },
      { test: (u) => u.includes('/me/push-preference'), status: 200, body: pushPreferenceOn },
    ]);

    renderRouter('src/app', { initialUrl: '/account' });

    await waitFor(() => expect(screen.getByTestId('account-link-google-button')).toBeVisible());
    expect(screen.getByText('Googleアカウントを連携')).toBeVisible();
    expect(screen.getByTestId('account-link-email-toggle')).toBeVisible();
    expect(screen.getByText('メールアドレス＋パスワードを追加')).toBeVisible();
    expect(screen.getByTestId('account-change-password-toggle')).toBeVisible();
    expect(screen.getByTestId('account-resend-verification-button')).toBeVisible();

    expect(screen.queryByText(/WordPress/i)).toBeNull();
    expect(screen.queryByText(/Application Password/i)).toBeNull();
  });
});
