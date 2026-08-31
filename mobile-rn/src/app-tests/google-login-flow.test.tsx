import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

// Mocked at the boundary this app owns (src/auth/googleSignIn.ts), not
// the native @react-native-google-signin/google-signin module itself -
// the Jest/RN test renderer never has the real native RNGoogleSignin
// module registered, so signInWithGoogleDiagnostic()'s own internal
// TurboModuleRegistry/NativeModules probes would always report "not
// found" here, independent of what's actually being tested. Mocked to
// simulate the full successful staged flow; see
// google-login-module-not-found.test.tsx for the "not found" case.
jest.mock('@/auth/googleSignIn', () => ({
  ...jest.requireActual('@/auth/googleSignIn'),
  // isGoogleSignInAvailable() would otherwise be false under Jest (the
  // real, unmocked module-status probe reports RNGoogleSignin as "not
  // found" here, same as it does on every real Web deploy) - forced true
  // so this success-path test can still exercise the button itself. See
  // google-login-module-not-found.test.tsx for the real, unmocked "hidden"
  // case this test deliberately overrides.
  isGoogleSignInAvailable: jest.fn(() => true),
  signInWithGoogleDiagnostic: jest.fn(async () => ({
    ok: true,
    idToken: 'mock-google-id-token',
    steps: [
      { step: "TurboModuleRegistry.get('RNGoogleSignin')", status: 'ok', detail: 'found' },
      { step: 'NativeModules.RNGoogleSignin', status: 'ok', detail: 'found' },
      { step: 'GoogleSignin.configure()', status: 'ok', detail: 'webClientId="mock"' },
      { step: 'GoogleSignin.hasPlayServices()', status: 'ok', detail: 'succeeded' },
      { step: 'GoogleSignin.signIn()', status: 'ok', detail: 'succeeded, idToken received' },
    ],
  })),
}));

/** Kept in its own file - see login-flow.test.tsx's docblock for why
 * every renderRouter() call needs a dedicated file in this codebase. */
describe('login flow: Google', () => {
  it('pressing "Googleで続ける" exchanges the Google ID Token for a StageArt session and navigates to /home', async () => {
    mockFetchRoutes([
      {
        test: (u) => u.endsWith('/auth/google'),
        status: 200,
        body: {
          access_token: 'access-token-1',
          refresh_token: 'refresh-token-1',
          token_type: 'Bearer',
          expires_in: 3600,
          person_id: 'person-1',
          user_account_id: 'account-1',
          is_new_user: true,
        },
      },
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: { upcoming_rehearsals: [], notifications: [], followed_organizations_feed: [] } },
    ]);

    renderRouter('src/app', { initialUrl: '/login' });

    await waitFor(() => expect(screen.getByTestId('login-google-button')).toBeVisible());

    fireEvent.press(screen.getByTestId('login-google-button'));

    await waitFor(() => expect(screen.getByTestId('home-primary-nav')).toBeVisible());

    const googleCall = (global.fetch as jest.Mock).mock.calls.find(([url]) => String(url).endsWith('/auth/google'));
    expect(googleCall).toBeDefined();
    const [, init] = googleCall as [unknown, RequestInit & { body: string }];
    expect(JSON.parse(init.body)).toEqual({ id_token: 'mock-google-id-token' });
  });
});
