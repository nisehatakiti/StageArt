import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

import { AuthProvider } from '@/auth/AuthContext';
import { clearEmailHandoff, peekEmailHandoff } from '@/auth/loginEmailHandoff';

import LoginScreen from '../app/login';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

jest.mock('@/auth/googleSignIn', () => ({
  ...jest.requireActual('@/auth/googleSignIn'),
  isGoogleSignInAvailable: jest.fn(() => true),
}));

const mockRouter = { push: jest.fn(), replace: jest.fn(), setParams: jest.fn(), back: jest.fn() };

jest.mock('expo-router', () => ({
  ...jest.requireActual('expo-router'),
  useRouter: () => mockRouter,
}));

type Answer = { status: number; body: unknown } | 'network-error';

/** fetch mock: POST /auth/account/check answers per address; every request is recorded. */
function mockCheck(answers: Record<string, Answer>): { url: string; body: unknown }[] {
  const calls: { url: string; body: unknown }[] = [];

  global.fetch = jest.fn(async (input: unknown, init?: RequestInit) => {
    const url = String(input);
    const body = typeof init?.body === 'string' ? JSON.parse(init.body) : undefined;
    calls.push({ url, body });

    if (url.endsWith('/auth/account/check')) {
      const answer = answers[(body as { email: string }).email];

      if (answer === 'network-error' || answer === undefined) {
        throw new TypeError('Network request failed');
      }

      return { ok: answer.status < 300, status: answer.status, text: async () => JSON.stringify(answer.body), json: async () => answer.body } as Response;
    }

    throw new Error(`Unmocked fetch: ${url}`);
  }) as jest.Mock;

  return calls;
}

const ok = (status: string, methods: string[]): Answer => ({ status: 200, body: { status, methods } });

function renderLogin() {
  render(
    <AuthProvider>
      <LoginScreen />
    </AuthProvider>
  );
}

async function typeEmailAndNext(email: string) {
  await waitFor(() => expect(screen.getByTestId('login-email')).toBeVisible());
  fireEvent.changeText(screen.getByTestId('login-email'), email);
  await waitFor(() => expect(screen.getByTestId('login-email').props.value).toBe(email));
  fireEvent.press(screen.getByTestId('login-next'));
}

describe('login step 1: 次へ checks the account and leads to the matching step', () => {
  beforeEach(() => {
    mockRouter.push.mockClear();
    mockRouter.replace.mockClear();
    clearEmailHandoff('register');
    clearEmailHandoff('login');
  });

  it('sends only the address to POST /auth/account/check, and nothing before 次へ', async () => {
    const calls = mockCheck({ 'pw@example.com': ok('PASSWORD_LOGIN', ['password']) });
    renderLogin();

    await waitFor(() => expect(screen.getByTestId('login-email')).toBeVisible());
    expect(calls).toHaveLength(0);
    expect(screen.queryByTestId('login-password')).toBeNull();

    await typeEmailAndNext('pw@example.com');

    await waitFor(() => expect(screen.getByTestId('login-password')).toBeVisible());
    expect(calls).toHaveLength(1);
    expect(calls[0].url).toMatch(/\/auth\/account\/check$/);
    expect(calls[0].body).toEqual({ email: 'pw@example.com' });
  });

  it('step 1 offers 「Googleで続ける」 from the start, before any address or check', async () => {
    const calls = mockCheck({});
    renderLogin();

    await waitFor(() => expect(screen.getByTestId('login-google-button')).toBeVisible());
    expect(screen.getByTestId('login-email').props.value).toBe('');
    expect(screen.getByTestId('login-next')).toBeVisible();
    expect(screen.getByTestId('login-register-link')).toBeVisible();
    expect(screen.queryByTestId('login-password')).toBeNull();
    expect(screen.queryByTestId('login-account-notice')).toBeNull();

    // Typing an address does not hide it either (still before the check).
    fireEvent.changeText(screen.getByTestId('login-email'), 'someone@example.com');
    await waitFor(() => expect(screen.getByTestId('login-email').props.value).toBe('someone@example.com'));
    expect(screen.getByTestId('login-google-button')).toBeVisible();
    expect(calls).toHaveLength(0);
  });

  it('a password account goes to the password step (no Google button)', async () => {
    mockCheck({ 'pw@example.com': ok('PASSWORD_LOGIN', ['password']) });
    renderLogin();
    await typeEmailAndNext('pw@example.com');

    await waitFor(() => expect(screen.getByTestId('login-submit')).toBeVisible());
    expect(screen.getByTestId('login-forgot-password-link')).toBeVisible();
    expect(screen.queryByTestId('login-google-button')).toBeNull();
    expect(screen.getByTestId('login-email').props.value).toBe('pw@example.com');
  });

  it('an account with both methods shows the password step and Google', async () => {
    mockCheck({ 'both@example.com': ok('MULTIPLE_AUTH_METHODS', ['password', 'google']) });
    renderLogin();
    await typeEmailAndNext('both@example.com');

    await waitFor(() => expect(screen.getByTestId('login-password')).toBeVisible());
    expect(screen.getByTestId('login-google-button')).toBeVisible();
  });

  it('an unverified account explains the confirmation and keeps the password step (it leads to the resend screen)', async () => {
    mockCheck({ 'new@example.com': ok('EMAIL_VERIFICATION_REQUIRED', []) });
    renderLogin();
    await typeEmailAndNext('new@example.com');

    await waitFor(() => expect(screen.getByTestId('login-account-notice')).toBeVisible());
    expect(screen.getByTestId('login-account-notice').props.children).toContain('確認がまだ済んでいません');
    expect(screen.getByTestId('login-password')).toBeVisible();
    expect(screen.getByTestId('login-submit')).toBeVisible();
    // The address may have been registered with a password by someone else
    // and never verified - a Google user must still find Google here.
    expect(screen.getByTestId('login-google-button')).toBeVisible();
    expect(screen.getByTestId('login-account-notice').props.children).toContain('Googleアカウントで登録している場合は、「Googleで続ける」からログインしてください。');
  });

  it('an unverified account with a Google identity says Google can be used too', async () => {
    mockCheck({ 'new-g@example.com': ok('EMAIL_VERIFICATION_REQUIRED', ['google']) });
    renderLogin();
    await typeEmailAndNext('new-g@example.com');

    await waitFor(() => expect(screen.getByTestId('login-account-notice')).toBeVisible());
    expect(screen.getByTestId('login-account-notice').props.children).toContain('Googleでもログインできます。');
    expect(screen.getByTestId('login-password')).toBeVisible();
    expect(screen.getByTestId('login-google-button')).toBeVisible();
  });

  it('a Google-only account is led to Google, not to registration or a password', async () => {
    mockCheck({ 'gmail-user@gmail.com': ok('GOOGLE_LOGIN', ['google']) });
    renderLogin();
    await typeEmailAndNext('gmail-user@gmail.com');

    await waitFor(() => expect(screen.getByTestId('login-google-button')).toBeVisible());
    expect(screen.getByTestId('login-account-notice').props.children).toContain('Googleアカウントで登録されています');
    expect(screen.queryByTestId('login-password')).toBeNull();
    expect(screen.queryByTestId('login-go-register')).toBeNull();
  });

  it('an unregistered address is led to registration, with the address handed over (memory only)', async () => {
    mockCheck({ 'nobody@example.com': ok('NOT_FOUND', []) });
    renderLogin();
    await typeEmailAndNext('nobody@example.com');

    await waitFor(() => expect(screen.getByTestId('login-go-register')).toBeVisible());
    expect(screen.getByTestId('login-account-notice').props.children).toBe(
      'このメールアドレスはまだ登録されていません。新規登録を行ってください。Googleアカウントで登録している場合は、「Googleで続ける」からログインしてください。'
    );
    expect(screen.queryByTestId('login-password')).toBeNull();
    // A Google user whose notification address is missing or different is
    // answered NOT_FOUND too: Google stays next to registration.
    expect(screen.getByTestId('login-google-button')).toBeVisible();

    fireEvent.press(screen.getByTestId('login-go-register'));

    expect(mockRouter.push).toHaveBeenCalledWith('/register');
    expect(peekEmailHandoff('register')).toBe('nobody@example.com');
  });

  it('a blocked account is told it cannot be used - no login form, no registration', async () => {
    mockCheck({ 'blocked@example.com': ok('DISABLED', []) });
    renderLogin();
    await typeEmailAndNext('blocked@example.com');

    await waitFor(() => expect(screen.getByTestId('login-account-notice')).toBeVisible());
    expect(screen.getByTestId('login-account-notice').props.children).toContain('ご利用いただけません');
    expect(screen.queryByTestId('login-password')).toBeNull();
    expect(screen.queryByTestId('login-google-button')).toBeNull();
    expect(screen.queryByTestId('login-go-register')).toBeNull();
  });

  it('「メールアドレスを入力し直す」 returns to step 1 with the address kept', async () => {
    mockCheck({ 'pw@example.com': ok('PASSWORD_LOGIN', ['password']) });
    renderLogin();
    await typeEmailAndNext('pw@example.com');
    await waitFor(() => expect(screen.getByTestId('login-password')).toBeVisible());

    fireEvent.press(screen.getByTestId('login-back'));

    await waitFor(() => expect(screen.getByTestId('login-next')).toBeVisible());
    expect(screen.queryByTestId('login-password')).toBeNull();
    expect(screen.getByTestId('login-email').props.value).toBe('pw@example.com');
  });

  it('changing the address after a check goes back to step 1 (the new address is checked again)', async () => {
    const calls = mockCheck({ 'pw@example.com': ok('PASSWORD_LOGIN', ['password']), 'nobody@example.com': ok('NOT_FOUND', []) });
    renderLogin();
    await typeEmailAndNext('pw@example.com');
    await waitFor(() => expect(screen.getByTestId('login-password')).toBeVisible());

    fireEvent.changeText(screen.getByTestId('login-email'), 'nobody@example.com');

    await waitFor(() => expect(screen.getByTestId('login-next')).toBeVisible());
    expect(screen.queryByTestId('login-password')).toBeNull();
    fireEvent.press(screen.getByTestId('login-next'));
    await waitFor(() => expect(screen.getByTestId('login-go-register')).toBeVisible());
    expect(calls.map((c) => (c.body as { email: string }).email)).toEqual(['pw@example.com', 'nobody@example.com']);
  });

  it('an empty address is not sent', async () => {
    const calls = mockCheck({});
    renderLogin();
    await waitFor(() => expect(screen.getByTestId('login-next')).toBeVisible());

    fireEvent.press(screen.getByTestId('login-next'));

    await waitFor(() => expect(screen.getByTestId('login-error')).toBeVisible());
    expect(calls).toHaveLength(0);
  });

  it('a malformed address (422) stays on step 1 with a message', async () => {
    mockCheck({ 'bad@': { status: 422, body: { code: 'stageart_account_check_invalid_email', message: 'x', data: { status: 422 } } } });
    renderLogin();
    await typeEmailAndNext('bad@');

    await waitFor(() => expect(screen.getByTestId('login-error').props.children).toBe('メールアドレスの形式が正しくありません。'));
    expect(screen.getByTestId('login-next')).toBeVisible();
    expect(screen.queryByTestId('login-password')).toBeNull();
  });

  it('when the check is rate-limited (429) the ordinary login form is offered, so nobody is locked out', async () => {
    mockCheck({ 'pw@example.com': { status: 429, body: { code: 'stageart_rate_limited', message: 'x', data: { status: 429, retry_after: 300 } } } });
    renderLogin();
    await typeEmailAndNext('pw@example.com');

    await waitFor(() => expect(screen.getByTestId('login-password')).toBeVisible());
    expect(screen.getByTestId('login-google-button')).toBeVisible();
    expect(screen.getByTestId('login-error').props.children).toContain('確認の回数が多い');
    expect(screen.getByTestId('login-retry-check')).toBeVisible();
  });

  it('when the check cannot be reached it can be retried, and the ordinary form is offered meanwhile', async () => {
    const calls = mockCheck({ 'pw@example.com': 'network-error' });
    renderLogin();
    await typeEmailAndNext('pw@example.com');

    await waitFor(() => expect(screen.getByTestId('login-retry-check')).toBeVisible());
    expect(screen.getByTestId('login-password')).toBeVisible();

    fireEvent.press(screen.getByTestId('login-retry-check'));
    await waitFor(() => expect(calls).toHaveLength(2));
  });

  it('a second press while the check is in flight sends nothing more', async () => {
    let release: (() => void) | null = null;
    const calls: string[] = [];
    global.fetch = jest.fn((input: unknown) => {
      calls.push(String(input));
      return new Promise<Response>((resolve) => {
        release = () => resolve({ ok: true, status: 200, text: async () => JSON.stringify({ status: 'PASSWORD_LOGIN', methods: ['password'] }), json: async () => ({}) } as Response);
      });
    }) as jest.Mock;
    renderLogin();
    await typeEmailAndNext('pw@example.com');

    await waitFor(() => expect(calls).toHaveLength(1));
    fireEvent.press(screen.getByTestId('login-next'));
    fireEvent.press(screen.getByTestId('login-next'));
    expect(calls).toHaveLength(1);

    release!();
    await waitFor(() => expect(screen.getByTestId('login-password')).toBeVisible());
  });
});

/**
 * Kept last in this file: a successful sign-in leaves AuthContext signed in
 * for the rest of the file (module state), which the step-1 tests above must
 * not see.
 */
describe('login step 2: the password form', () => {
  beforeEach(() => {
    mockRouter.replace.mockClear();
  });

  it('PASSWORD_LOGIN: the password step signs in with the address and the password', async () => {
    mockCheck({ 'pw@example.com': ok('PASSWORD_LOGIN', ['password']) });
    renderLogin();
    await typeEmailAndNext('pw@example.com');
    await waitFor(() => expect(screen.getByTestId('login-password')).toBeVisible());

    const checkFetch = global.fetch as jest.Mock;
    const loginBodies: unknown[] = [];
    const answer = (body: unknown) => ({ ok: true, status: 200, text: async () => JSON.stringify(body), json: async () => body }) as Response;
    global.fetch = jest.fn(async (input: unknown, init?: RequestInit) => {
      const url = String(input);
      if (url.endsWith('/auth/email/login')) {
        loginBodies.push(JSON.parse(String(init?.body)));
        return answer({ access_token: 'access-token-1', refresh_token: 'refresh-token-1', token_type: 'Bearer', expires_in: 3600, person_id: 'person-1', user_account_id: 'account-1', is_new_user: false });
      }
      if (url.endsWith('/me')) {
        return answer({ email_verified: true, family_name: '山田', given_name: '花子' });
      }
      return checkFetch(input, init);
    }) as jest.Mock;

    fireEvent.changeText(screen.getByTestId('login-password'), 'secret-pass');
    await waitFor(() => expect(screen.getByTestId('login-password').props.value).toBe('secret-pass'));
    fireEvent.press(screen.getByTestId('login-submit'));

    await waitFor(() => expect(mockRouter.replace).toHaveBeenCalledWith('/home'));
    expect(loginBodies).toEqual([{ email: 'pw@example.com', password: 'secret-pass' }]);
  });
});
