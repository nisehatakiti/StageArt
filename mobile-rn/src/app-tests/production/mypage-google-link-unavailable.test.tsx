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
 * StageArt mobile-rn 修正指示書 §6: the counterpart to
 * mypage-account-linking-render.test.tsx's forced-available case - this
 * test deliberately does NOT mock googleSignIn.ts, so the real,
 * unmocked isGoogleSignInAvailable() runs (always false under Jest's
 * test renderer, same as on every real Web deploy - see that function's
 * own docblock). "Googleアカウントを連携" must not be offered when it can
 * only ever fail; every other Security action stays available.
 */
describe('My Page: Google linking hidden when unavailable', () => {
  it('does not render "Googleアカウントを連携" but keeps the other Security actions', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne },
      { test: (u) => u.endsWith('/me'), status: 200, body: currentPerson },
      { test: (u) => u.includes('/me/push-preference'), status: 200, body: pushPreferenceOn },
    ]);

    renderRouter('src/app', { initialUrl: '/production/prod-1/mypage' });

    await waitFor(() => expect(screen.getByTestId('mypage-change-password-toggle')).toBeVisible());

    expect(screen.queryByTestId('mypage-link-google-button')).toBeNull();
    expect(screen.queryByText('Googleアカウントを連携')).toBeNull();
    expect(screen.getByTestId('mypage-link-email-toggle')).toBeVisible();
    expect(screen.getByTestId('mypage-resend-verification-button')).toBeVisible();
  });
});
