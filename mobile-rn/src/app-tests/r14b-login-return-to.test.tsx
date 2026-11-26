import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

import { AuthProvider } from '@/auth/AuthContext';

import { mockFetchRoutes, myDashboardEmpty } from './__fixtures__/homeFixtures';
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
 * PR #7 R14-B stage 9 (design doc §10 「ログイン」, §14.2, R13 Medium):
 * after login the person goes to return_to when it is an in-app path. (A
 * URL of another site is never followed - see utils/returnTo.test.ts and
 * r14b-login-return-to-routing.test.tsx.)
 *
 * Two-step login (address -> 次へ -> password -> ログイン) with the real
 * AuthProvider and requests; the screen is rendered on its own with the
 * URL's return_to as its route param, because the full-router harness does
 * not re-render this screen's steps (see login-flow.test.tsx).
 */
describe('R14-B ログイン: return_to', () => {
  it('returns to /participant-invitations after logging in', async () => {
    mockParams = { return_to: '/participant-invitations' };
    mockFetchRoutes([
      { test: (u) => u.endsWith('/auth/account/check'), status: 200, body: { status: 'PASSWORD_LOGIN', methods: ['password'] } },
      {
        test: (u) => u.endsWith('/auth/email/login'),
        status: 200,
        body: { access_token: 'access-token-1', refresh_token: 'refresh-token-1', token_type: 'Bearer', expires_in: 3600 },
      },
      { test: (u) => u.endsWith('/me/participant-invitations'), status: 200, body: [] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
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

    await waitFor(() => expect(mockRouter.replace).toHaveBeenCalledWith('/participant-invitations'));
    expect(mockRouter.replace).toHaveBeenCalledTimes(1);
  });
});
