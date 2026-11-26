import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

import { AuthProvider } from '@/auth/AuthContext';

import { mockFetchRoutes } from './__fixtures__/homeFixtures';
import LoginScreen from '../app/login';

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

const mockRouter = { push: jest.fn(), replace: jest.fn(), setParams: jest.fn(), back: jest.fn() };

jest.mock('expo-router', () => ({
  ...jest.requireActual('expo-router'),
  useRouter: () => mockRouter,
}));

/** Address -> 次へ reports a Google account -> 「Googleで続ける」 -> /home.
 * Rendered on its own with a mocked router (see login-flow.test.tsx). */
describe('login flow: Google', () => {
  it('pressing "Googleで続ける" exchanges the Google ID Token for a StageArt session and navigates to /home', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/auth/account/check'), status: 200, body: { status: 'GOOGLE_LOGIN', methods: ['google'] } },
      { test: (u) => u.endsWith('/me'), status: 200, body: { email_verified: true, family_name: '山田', given_name: '花子' } },
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

    render(
      <AuthProvider>
        <LoginScreen />
      </AuthProvider>
    );

    await waitFor(() => expect(screen.getByTestId('login-email')).toBeVisible());
    expect(screen.queryByTestId('login-google-button')).toBeNull();
    fireEvent.changeText(screen.getByTestId('login-email'), 'gmail-user@gmail.com');
    await waitFor(() => expect(screen.getByTestId('login-email').props.value).toBe('gmail-user@gmail.com'));
    fireEvent.press(screen.getByTestId('login-next'));

    await waitFor(() => expect(screen.getByTestId('login-google-button')).toBeVisible());
    expect(screen.queryByTestId('login-password')).toBeNull();

    fireEvent.press(screen.getByTestId('login-google-button'));

    // StageArt Home仕様追加 (2026-09-05): home-primary-nav is now
    // conditional on having favorites - home-quick-action-create-organization
    // is the unconditional "we've reached Home" marker instead.
    // R7: the 「団体を作る」 quick action this test waited for was removed from
    // Home (2513c52); Home's root testID is the "Home has been reached" marker.
    await waitFor(() => expect(mockRouter.replace).toHaveBeenCalledWith('/home'));

    const googleCall = (global.fetch as jest.Mock).mock.calls.find(([url]) => String(url).endsWith('/auth/google'));
    expect(googleCall).toBeDefined();
    const [, init] = googleCall as [unknown, RequestInit & { body: string }];
    expect(JSON.parse(init.body)).toEqual({ id_token: 'mock-google-id-token' });
  });
});
