import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

/**
 * PR #7 R14-B (design doc §10 「ログイン」, §14.2, R13 Medium; approved fix A):
 * where login sends the person afterwards, for email and Google login.
 * - With a name: to the checked return_to, else /home.
 * - Without a name: to set-name first, carrying the checked return_to (and
 *   Google's name hints), so set-name can continue there.
 * - A return_to that is not an in-app path is dropped.
 *
 * The screen is rendered directly with its router / auth / Google module
 * mocked: the full-router harness does not reflect a second state change
 * reliably (see the stage-9 report), and this checks the routing decision
 * itself.
 */
const mockReplace = jest.fn();
let mockParams: Record<string, string | undefined> = {};
const mockLoginWithEmail = jest.fn();
const mockLoginWithGoogle = jest.fn();

jest.mock('expo-router', () => ({
  useRouter: () => ({ replace: mockReplace, push: jest.fn() }),
  useLocalSearchParams: () => mockParams,
  Redirect: () => null,
}));
jest.mock('@/auth/AuthContext', () => ({
  useAuth: () => ({ loginWithEmail: mockLoginWithEmail, loginWithGoogle: mockLoginWithGoogle }),
}));
jest.mock('@/hooks/use-site-host', () => ({ useIsPublicSite: () => false }));
jest.mock('@/auth/googleSignIn', () => ({
  isGoogleSignInAvailable: () => true,
  signInWithGoogleDiagnostic: async () => ({ ok: true, idToken: 'google-id-token', steps: [] }),
}));

// eslint-disable-next-line @typescript-eslint/no-require-imports
const LoginScreen = require('@/app/login').default;

const signedIn = (hasName: boolean, hints: { family: string | null; given: string | null } = { family: null, given: null }) => ({
  ok: true,
  emailVerified: true,
  hasName,
  familyNameHint: hints.family,
  givenNameHint: hints.given,
});

async function loginByEmail() {
  await render(<LoginScreen />);
  await fireEvent.changeText(screen.getByTestId('login-email'), 'member@example.com');
  await fireEvent.changeText(screen.getByTestId('login-password'), 'password123');
  await fireEvent.press(screen.getByTestId('login-submit'));
  await waitFor(() => expect(mockReplace).toHaveBeenCalled());
  return mockReplace.mock.calls[0][0];
}

async function loginByGoogle() {
  await render(<LoginScreen />);
  await fireEvent.press(screen.getByTestId('login-google-button'));
  await waitFor(() => expect(mockReplace).toHaveBeenCalled());
  expect(mockLoginWithGoogle).toHaveBeenCalledWith('google-id-token');
  return mockReplace.mock.calls[0][0];
}

beforeEach(() => {
  mockReplace.mockClear();
  mockLoginWithEmail.mockReset();
  mockLoginWithGoogle.mockReset();
});

describe('login: return_to (email)', () => {
  it('a person with a name goes to the checked return_to', async () => {
    mockParams = { return_to: '/participant-invitations' };
    mockLoginWithEmail.mockResolvedValue(signedIn(true));
    expect(await loginByEmail()).toBe('/participant-invitations');
    expect(mockLoginWithEmail).toHaveBeenCalledWith('member@example.com', 'password123');
  });

  it('a person without a name sets it first, carrying return_to', async () => {
    mockParams = { return_to: '/participant-invitations' };
    mockLoginWithEmail.mockResolvedValue(signedIn(false));
    expect(await loginByEmail()).toEqual({ pathname: '/set-name', params: { return_to: '/participant-invitations' } });
  });

  it.each([
    ['another site', 'https://evil.example.com/'],
    ['protocol-relative', '//evil.example.com'],
    ['an auth screen', '/login'],
  ])('drops a return_to that is %s', async (_label, value) => {
    mockParams = { return_to: value };
    mockLoginWithEmail.mockResolvedValue(signedIn(true));
    expect(await loginByEmail()).toBe('/home');

    mockReplace.mockClear();
    mockLoginWithEmail.mockResolvedValue(signedIn(false));
    expect(await loginByEmail()).toBe('/set-name');
  });

  it('without return_to keeps the usual routes', async () => {
    mockParams = {};
    mockLoginWithEmail.mockResolvedValue(signedIn(true));
    expect(await loginByEmail()).toBe('/home');
    mockReplace.mockClear();
    mockLoginWithEmail.mockResolvedValue(signedIn(false));
    expect(await loginByEmail()).toBe('/set-name');
  });
});

describe('login: return_to (Google)', () => {
  it('a person with a name goes to the checked return_to', async () => {
    mockParams = { return_to: '/participant-invitations' };
    mockLoginWithGoogle.mockResolvedValue(signedIn(true));
    expect(await loginByGoogle()).toBe('/participant-invitations');
  });

  it('a person without a name sets it first with Google\'s hints and return_to', async () => {
    mockParams = { return_to: '/participant-invitations' };
    mockLoginWithGoogle.mockResolvedValue(signedIn(false, { family: '舞台', given: '花子' }));
    expect(await loginByGoogle()).toEqual({
      pathname: '/set-name',
      params: { family_name_hint: '舞台', given_name_hint: '花子', return_to: '/participant-invitations' },
    });
  });

  it('drops a return_to of another site', async () => {
    mockParams = { return_to: 'https://evil.example.com/' };
    mockLoginWithGoogle.mockResolvedValue(signedIn(false, { family: '舞台', given: '花子' }));
    expect(await loginByGoogle()).toEqual({ pathname: '/set-name', params: { family_name_hint: '舞台', given_name_hint: '花子' } });
  });
});
