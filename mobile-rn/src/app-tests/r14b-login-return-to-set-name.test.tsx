import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

import { AuthProvider } from '@/auth/AuthContext';

import { mockFetchRoutes } from './__fixtures__/homeFixtures';
import LoginScreen from '../app/login';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

const mockRouter = { push: jest.fn(), replace: jest.fn(), setParams: jest.fn(), back: jest.fn() };
let mockParams: Record<string, string> = {};

jest.mock('expo-router', () => ({
  ...jest.requireActual('expo-router'),
  useRouter: () => mockRouter,
  useLocalSearchParams: () => mockParams,
}));

/**
 * PR #7 R14-B (design doc §10 「ログイン」; approved fix A): a person
 * without a name who came from the invitation list logs in, is sent to the
 * name screen first, and that screen gets return_to=/participant-invitations
 * (where it continues after saving - r14b-set-name-return-to.test.tsx).
 *
 * Two-step login with the real AuthProvider and requests; the screen is
 * rendered on its own (see r14b-login-return-to.test.tsx for why).
 */
describe('R14-B ログイン: 名前未設定でも return_to を引き継ぐ', () => {
  it('goes to set-name with return_to', async () => {
    mockParams = { return_to: '/participant-invitations' };
    mockFetchRoutes([
      { test: (u) => u.endsWith('/auth/account/check'), status: 200, body: { status: 'PASSWORD_LOGIN', methods: ['password'] } },
      {
        test: (u) => u.endsWith('/auth/email/login'),
        status: 200,
        body: { access_token: 'access-token-1', refresh_token: 'refresh-token-1', token_type: 'Bearer', expires_in: 3600 },
      },
      {
        test: (u) => u.endsWith('/me'),
        status: 200,
        body: { id: 'person-1', word_press_user_id: 1, email_verified: true, family_name: null, given_name: null },
      },
    ]);

    render(
      <AuthProvider>
        <LoginScreen />
      </AuthProvider>
    );

    await waitFor(() => expect(screen.getByTestId('login-register-link')).toBeVisible());
    fireEvent.changeText(screen.getByTestId('login-email'), 'member@example.com');
    await waitFor(() => expect(screen.getByTestId('login-email').props.value).toBe('member@example.com'));
    fireEvent.press(screen.getByTestId('login-next'));
    await waitFor(() => expect(screen.getByTestId('login-password')).toBeVisible());
    fireEvent.changeText(screen.getByTestId('login-password'), 'password123');
    await waitFor(() => expect(screen.getByTestId('login-password').props.value).toBe('password123'));
    fireEvent.press(screen.getByTestId('login-submit'));

    await waitFor(() => expect(mockRouter.replace).toHaveBeenCalled());
    expect(mockRouter.replace).toHaveBeenCalledTimes(1);
    expect(mockRouter.replace).toHaveBeenCalledWith({ pathname: '/set-name', params: { return_to: '/participant-invitations' } });
  });
});
