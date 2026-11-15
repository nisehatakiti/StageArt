import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

// isGoogleSignInAvailable() would otherwise be false under Jest (the real
// module-status probe reports RNGoogleSignin "not found" here, same as a
// real Web deploy) - forced true so this render test can still assert on
// the Google button's presence alongside every other login-screen element.
jest.mock('@/auth/googleSignIn', () => ({
  ...jest.requireActual('@/auth/googleSignIn'),
  isGoogleSignInAvailable: jest.fn(() => true),
}));

/** Kept in its own file - see login-flow.test.tsx's docblock for why
 * every renderRouter() call needs a dedicated file in this codebase.
 *
 * §"ログイン画面" / §13 of this Phase's instruction: the official
 * StageArt Mobile login screen structure (Google primary + Email/
 * Password secondary + register/forgot-password links), and the
 * explicit absence of any WordPress/Application Password/username
 * concept anywhere on it. */
describe('Login screen', () => {
  beforeEach(() => {
    global.fetch = jest.fn();
  });

  it('shows the Google button, Email+Password fields, and the register/forgot-password links - with no WordPress terms', async () => {
    renderRouter('src/app', { initialUrl: '/login' });

    await waitFor(() => expect(screen.getByTestId('login-google-button')).toBeVisible());
    expect(screen.getByText('Googleで続ける')).toBeVisible();
    expect(screen.getByTestId('login-email')).toBeVisible();
    expect(screen.getByTestId('login-password')).toBeVisible();
    expect(screen.getByTestId('login-submit')).toBeVisible();
    expect(screen.getByTestId('login-register-link')).toBeVisible();
    expect(screen.getByTestId('login-forgot-password-link')).toBeVisible();

    expect(screen.queryByText(/WordPress/i)).toBeNull();
    expect(screen.queryByText(/Application Password/i)).toBeNull();
    expect(screen.queryByText('ユーザー名')).toBeNull();
    expect(global.fetch).not.toHaveBeenCalled();
  });
});
