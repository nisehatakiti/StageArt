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

jest.mock('expo-router', () => ({
  ...jest.requireActual('expo-router'),
  useRouter: () => mockRouter,
}));

/**
 * The e-mail login end to end on the login screen: address -> 次へ (POST
 * /auth/account/check) -> password -> ログイン (POST /auth/email/login) ->
 * /home. Rendered on its own with a mocked router: with the two-step
 * screen the steps are state changes, and renderRouter() does not deliver
 * text input or re-render this screen's state in this setup (the earlier
 * renderRouter() version of this test passed without the typed values
 * ever reaching the screen).
 */
describe('login flow', () => {
  it('a successful Email+Password login sends the typed credentials and navigates to /home', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/auth/account/check'), status: 200, body: { status: 'PASSWORD_LOGIN', methods: ['password'] } },
      {
        test: (u) => u.endsWith('/auth/email/login'),
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
      { test: (u) => u.endsWith('/me'), status: 200, body: { email_verified: true, family_name: '山田', given_name: '花子' } },
    ]);

    render(
      <AuthProvider>
        <LoginScreen />
      </AuthProvider>
    );

    await waitFor(() => expect(screen.getByTestId('login-email')).toBeVisible());
    fireEvent.changeText(screen.getByTestId('login-email'), 'person@example.com');
    await waitFor(() => expect(screen.getByTestId('login-email').props.value).toBe('person@example.com'));
    fireEvent.press(screen.getByTestId('login-next'));

    await waitFor(() => expect(screen.getByTestId('login-password')).toBeVisible());
    fireEvent.changeText(screen.getByTestId('login-password'), 'password123');
    await waitFor(() => expect(screen.getByTestId('login-password').props.value).toBe('password123'));
    fireEvent.press(screen.getByTestId('login-submit'));

    await waitFor(() => expect(mockRouter.replace).toHaveBeenCalledWith('/home'));

    const calls = (global.fetch as jest.Mock).mock.calls as [unknown, RequestInit & { body?: string }][];
    const check = calls.find(([url]) => String(url).endsWith('/auth/account/check'));
    const login = calls.find(([url]) => String(url).endsWith('/auth/email/login'));
    expect(JSON.parse(check![1].body as string)).toEqual({ email: 'person@example.com' });
    expect(JSON.parse(login![1].body as string)).toEqual({ email: 'person@example.com', password: 'password123' });
  });
});
