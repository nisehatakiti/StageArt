import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

// Mocked at the boundary this app owns (src/auth/googleSignIn.ts), not
// the native @react-native-google-signin/google-signin module itself -
// see that file's own docblock for why it is imported dynamically
// (native module not present in every installed dev build) and why
// mocking the wrapper, rather than the native library, is the right
// seam for this test.
jest.mock('@/auth/googleSignIn', () => ({
  ...jest.requireActual('@/auth/googleSignIn'),
  signInWithGoogle: jest.fn(async () => 'mock-google-id-token'),
}));

// The Jest/RN test renderer never has the real native RNGoogleSignin
// module registered (same underlying reason as the mock above), so
// login.tsx's pre-flight checkGoogleSigninModuleStatus() probe against
// the real react-native TurboModuleRegistry/NativeModules would always
// report "not found" here, independent of what's actually being tested.
// Mocked to simulate "module is present" so this test can exercise the
// happy path; see google-login-module-not-found.test.tsx for the
// diagnostic gate itself.
jest.mock('@/auth/googleModuleDiagnostics', () => ({
  ...jest.requireActual('@/auth/googleModuleDiagnostics'),
  checkGoogleSigninModuleStatus: jest.fn(() => ({
    turboModuleFound: true,
    legacyBridgeModuleFound: false,
    otherGoogleRelatedKeys: [],
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
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: { upcoming_rehearsals: [], notifications: [] } },
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
