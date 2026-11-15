import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { currentPerson, mockFetchRoutes, pushPreferenceOn } from './__fixtures__/productionShellFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * StageArt mobile-rn 修正指示書 §6, superseded by StageArt Blueprint再構成
 * Phase 1d: the counterpart to mypage-account-linking-render.test.tsx's
 * forced-available case, now against `/account` - this test deliberately
 * does NOT mock googleSignIn.ts, so the real, unmocked
 * isGoogleSignInAvailable() runs (always false under Jest's test
 * renderer, same as on every real Web deploy). "Googleアカウントを連携"
 * must not be offered when it can only ever fail; every other Security
 * action stays available.
 */
describe('Account: Google linking hidden when unavailable', () => {
  it('does not render "Googleアカウントを連携" but keeps the other Security actions', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/me'), status: 200, body: currentPerson },
      { test: (u) => u.includes('/me/push-preference'), status: 200, body: pushPreferenceOn },
    ]);

    renderRouter('src/app', { initialUrl: '/account' });

    await waitFor(() => expect(screen.getByTestId('account-change-password-toggle')).toBeVisible());

    expect(screen.queryByTestId('account-link-google-button')).toBeNull();
    expect(screen.queryByText('Googleアカウントを連携')).toBeNull();
    expect(screen.getByTestId('account-link-email-toggle')).toBeVisible();
    expect(screen.getByTestId('account-resend-verification-button')).toBeVisible();
  });
});
