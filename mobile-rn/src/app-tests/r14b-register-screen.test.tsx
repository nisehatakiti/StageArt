import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

/**
 * PR #7 R14-B (design doc §9.2 / §10 / §14.2; approved test D): the
 * registration form itself - rendered directly, with its router / auth
 * mocked, because the full-router harness does not reflect typing on this
 * screen (see the stage-9 report).
 * - The address starts empty (no value from an invitation).
 * - The typed address and password are what is sent; no invitation token
 *   is passed (registerWithEmail gets exactly two arguments).
 * - With an old link's token the form is not shown, the person is moved
 *   to login with the fixed return_to=/participant-invitations (logged
 *   out) / the invitation list (logged in), and the token is never handed
 *   anywhere.
 */
const mockReplace = jest.fn();
const mockRegisterWithEmail = jest.fn();
let mockParams: Record<string, string | undefined> = {};
let mockStatus = 'unauthenticated';

jest.mock('expo-router', () => ({
  useRouter: () => ({ replace: mockReplace, back: jest.fn() }),
  useLocalSearchParams: () => mockParams,
  Redirect: ({ href }: { href: unknown }) => {
    mockReplace(href);
    return null;
  },
}));
jest.mock('@/auth/AuthContext', () => ({ useAuth: () => ({ registerWithEmail: mockRegisterWithEmail, status: mockStatus }) }));
jest.mock('@/hooks/use-site-host', () => ({ useIsPublicSite: () => false }));

// eslint-disable-next-line @typescript-eslint/no-require-imports
const RegisterScreen = require('@/app/register').default;

beforeEach(() => {
  mockReplace.mockClear();
  mockRegisterWithEmail.mockReset();
  mockParams = {};
  mockStatus = 'unauthenticated';
});

describe('register screen (form)', () => {
  it('starts empty and sends the typed address without a token', async () => {
    mockRegisterWithEmail.mockResolvedValue({ ok: true, emailVerified: false, hasName: false, familyNameHint: null, givenNameHint: null });

    await render(<RegisterScreen />);
    expect(screen.getByTestId('register-email').props.value).toBe('');
    expect(screen.getByTestId('register-email').props.editable).not.toBe(false);

    await fireEvent.changeText(screen.getByTestId('register-email'), ' invitee@example.com ');
    await fireEvent.changeText(screen.getByTestId('register-password'), 'password123');
    await fireEvent.press(screen.getByTestId('register-submit'));

    await waitFor(() => expect(mockRegisterWithEmail).toHaveBeenCalledTimes(1));
    expect(mockRegisterWithEmail.mock.calls[0]).toEqual(['invitee@example.com', 'password123']);
    expect(mockReplace).toHaveBeenCalledWith('/registration-pending?email=invitee%40example.com');
  });

  it.each([
    ['logged out', 'unauthenticated', { pathname: '/login', params: { return_to: '/participant-invitations' } }],
    ['logged in', 'authenticated', '/participant-invitations'],
  ])('with an old link\'s token (%s) moves on without showing the form or the token', async (_label, status, target) => {
    mockStatus = status;
    mockParams = { token: 'old-secret-token' };

    await render(<RegisterScreen />);

    expect(screen.queryByTestId('register-email')).toBeNull();
    expect(mockReplace).toHaveBeenCalledWith(target);
    expect(JSON.stringify(mockReplace.mock.calls)).not.toContain('old-secret-token');
    expect(mockRegisterWithEmail).not.toHaveBeenCalled();
  });

  it('waits for the session state before moving an old link on', async () => {
    mockStatus = 'loading';
    mockParams = { token: 'old-secret-token' };

    await render(<RegisterScreen />);

    expect(screen.queryByTestId('register-email')).toBeNull();
    expect(mockReplace).not.toHaveBeenCalled();
  });
});
