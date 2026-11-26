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
    // Offered on step 1 already (before the check); the check then keeps it.
    expect(screen.getByTestId('login-google-button')).toBeVisible();
    fireEvent.changeText(screen.getByTestId('login-email'), 'gmail-user@gmail.com');
    await waitFor(() => expect(screen.getByTestId('login-email').props.value).toBe('gmail-user@gmail.com'));
    fireEvent.press(screen.getByTestId('login-next'));

    // The button is already there; wait for the check's answer (the button
    // is disabled while the check runs).
    await waitFor(() => expect(screen.getByTestId('login-account-notice')).toBeVisible());
    expect(screen.getByTestId('login-google-button')).toBeVisible();
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

/**
 * 「Googleで続ける」 must never be lost to the account check: the check
 * recognises a Google account only by a verified notification address equal
 * to the one typed, so a Google user may well be answered NOT_FOUND or
 * EMAIL_VERIFICATION_REQUIRED. From step 1 and from those answers the
 * button runs the very same Google sign-in: only the Google ID Token is
 * sent to POST /auth/google (never the address typed on this screen), and
 * nothing is registered from the screen itself.
 */
describe('login flow: Google from step 1 and from answers that did not recognise a Google account', () => {
  const googleSignIn = jest.requireMock('@/auth/googleSignIn') as { signInWithGoogleDiagnostic: jest.Mock };

  function mockRoutes(check: { status: string; methods: string[] } | null) {
    mockFetchRoutes([
      ...(check ? [{ test: (u: string) => u.endsWith('/auth/account/check'), status: 200, body: check }] : []),
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
          is_new_user: false,
        },
      },
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: { upcoming_rehearsals: [], notifications: [], followed_organizations_feed: [] } },
    ]);
  }

  function fetchedUrls(): string[] {
    return (global.fetch as jest.Mock).mock.calls.map(([url]) => String(url));
  }

  /** Presses 「Googleで続ける」 and checks the existing Google sign-in ran: the ID Token only, then /home. */
  async function pressGoogleAndExpectSignIn() {
    fireEvent.press(screen.getByTestId('login-google-button'));

    await waitFor(() => expect(mockRouter.replace).toHaveBeenCalledWith('/home'));
    expect(googleSignIn.signInWithGoogleDiagnostic).toHaveBeenCalledTimes(1);
    const googleCalls = (global.fetch as jest.Mock).mock.calls.filter(([url]) => String(url).endsWith('/auth/google'));
    expect(googleCalls).toHaveLength(1);
    expect(JSON.parse((googleCalls[0][1] as RequestInit & { body: string }).body)).toEqual({ id_token: 'mock-google-id-token' });
    expect(fetchedUrls().some((url) => /\/auth\/email\/register$|\/user-accounts/.test(url))).toBe(false);
  }

  async function checkAddress(email: string) {
    fireEvent.changeText(screen.getByTestId('login-email'), email);
    await waitFor(() => expect(screen.getByTestId('login-email').props.value).toBe(email));
    fireEvent.press(screen.getByTestId('login-next'));
  }

  function renderLogin() {
    render(
      <AuthProvider>
        <LoginScreen />
      </AuthProvider>
    );
  }

  beforeEach(() => {
    mockRouter.replace.mockClear();
    mockRouter.push.mockClear();
    googleSignIn.signInWithGoogleDiagnostic.mockClear();
  });

  it('step 1 offers Google before any address is typed, and signs in without the account check', async () => {
    mockRoutes(null);
    renderLogin();

    await waitFor(() => expect(screen.getByTestId('login-google-button')).toBeVisible());
    expect(screen.getByText('Googleで続ける')).toBeVisible();
    expect(screen.getByTestId('login-next')).toBeVisible();
    expect(screen.getByText('または')).toBeVisible();
    expect(screen.getByTestId('login-email').props.value).toBe('');

    await pressGoogleAndExpectSignIn();
    expect(fetchedUrls().some((url) => url.endsWith('/auth/account/check'))).toBe(false);
  });

  it('NOT_FOUND keeps Google next to 「新規登録へ」, and Google signs in (nothing is registered)', async () => {
    mockRoutes({ status: 'NOT_FOUND', methods: [] });
    renderLogin();
    await waitFor(() => expect(screen.getByTestId('login-email')).toBeVisible());
    await checkAddress('other-address@example.com');

    await waitFor(() => expect(screen.getByTestId('login-go-register')).toBeVisible());
    expect(screen.getByTestId('login-google-button')).toBeVisible();
    expect(screen.getByTestId('login-account-notice').props.children).toContain('「Googleで続ける」からログインしてください');
    expect(screen.queryByTestId('login-password')).toBeNull();

    await pressGoogleAndExpectSignIn();
    expect(mockRouter.push).not.toHaveBeenCalled();
  });

  it('EMAIL_VERIFICATION_REQUIRED without a Google identity still offers Google beside the password form', async () => {
    mockRoutes({ status: 'EMAIL_VERIFICATION_REQUIRED', methods: [] });
    renderLogin();
    await waitFor(() => expect(screen.getByTestId('login-email')).toBeVisible());
    await checkAddress('squatted@example.com');

    await waitFor(() => expect(screen.getByTestId('login-password')).toBeVisible());
    expect(screen.getByTestId('login-google-button')).toBeVisible();
    expect(screen.getByText('または')).toBeVisible();

    await pressGoogleAndExpectSignIn();
  });

  it('after a PASSWORD_LOGIN answer, 「メールアドレスを入力し直す」 brings Google back', async () => {
    mockRoutes({ status: 'PASSWORD_LOGIN', methods: ['password'] });
    renderLogin();
    await waitFor(() => expect(screen.getByTestId('login-email')).toBeVisible());
    await checkAddress('pw@example.com');

    await waitFor(() => expect(screen.getByTestId('login-password')).toBeVisible());
    expect(screen.queryByTestId('login-google-button')).toBeNull();

    fireEvent.press(screen.getByTestId('login-back'));

    await waitFor(() => expect(screen.getByTestId('login-google-button')).toBeVisible());
    await pressGoogleAndExpectSignIn();
  });
});
