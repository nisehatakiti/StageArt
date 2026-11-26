import { render, fireEvent as rtlFireEvent, screen as rtlScreen, waitFor as rtlWaitFor } from '@testing-library/react-native';
import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { AuthProvider } from '@/auth/AuthContext';

import { mockFetchRoutes, myDashboardEmpty } from './__fixtures__/homeFixtures';
import LoginScreen from '../app/login';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

const mockRouter = { push: jest.fn(), replace: jest.fn(), setParams: jest.fn(), back: jest.fn() };
/** null: the real route params (the full-router test); set: the login screen rendered on its own. */
let mockParams: Record<string, string> | null = null;

jest.mock('expo-router', () => {
  const actual = jest.requireActual('expo-router');
  return {
    ...actual,
    useRouter: (...args: unknown[]) => (mockParams === null ? actual.useRouter(...args) : mockRouter),
    useLocalSearchParams: (...args: unknown[]) => (mockParams === null ? actual.useLocalSearchParams(...args) : mockParams),
  };
});

const OLD_TOKEN = 'old-invitation-token-123';

/** Every URL requested and the request bodies, to check the old token never appears in them. */
function requestsText(): string {
  return (global.fetch as jest.Mock).mock.calls.map(([url, init]: [string, RequestInit?]) => `${String(url)} ${String(init?.body ?? '')}`).join('\n');
}

/** The login screen's route params an old link leads to (checked through the real router below). */
const OLD_LINK_LOGIN_PARAMS = { return_to: '/participant-invitations' };

/**
 * PR #7 R14-B (design doc §9.2 / §10; approved final fix): an old invitation
 * link `/register?token=...` opened while logged out leads to login with
 * return_to=/participant-invitations and then to the invitation list. The old token is
 * never read: it appears in no URL, no navigation argument and no request.
 *
 * Two-step login: one test follows the old link through the real router to
 * the login screen (no typing needed there) and checks it gets exactly
 * OLD_LINK_LOGIN_PARAMS; the other logs in on the login screen rendered on
 * its own with those params, because the full-router harness does not
 * re-render the login screen's steps (see login-flow.test.tsx). The
 * standalone render runs first: after a full-router render in the same
 * file it does not mount in this harness.
 *
 * The case without a name is in r14b-old-invitation-link-login-no-name.test.tsx;
 * the invitation list itself (empty state): participant-invitations tests.
 */
describe('R14-B 古い招待リンク: ログイン後の遷移', () => {
  it('a person with a name ends on the invitation list', async () => {
    mockParams = OLD_LINK_LOGIN_PARAMS;
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

    await rtlWaitFor(() => expect(rtlScreen.getByTestId('login-email')).toBeVisible());
    rtlFireEvent.changeText(rtlScreen.getByTestId('login-email'), 'member@example.com');
    await rtlWaitFor(() => expect(rtlScreen.getByTestId('login-email').props.value).toBe('member@example.com'));
    rtlFireEvent.press(rtlScreen.getByTestId('login-next'));
    await rtlWaitFor(() => expect(rtlScreen.getByTestId('login-password')).toBeVisible());
    rtlFireEvent.changeText(rtlScreen.getByTestId('login-password'), 'password123');
    await rtlWaitFor(() => expect(rtlScreen.getByTestId('login-password').props.value).toBe('password123'));
    rtlFireEvent.press(rtlScreen.getByTestId('login-submit'));

    await rtlWaitFor(() => expect(mockRouter.replace).toHaveBeenCalled());
    expect(mockRouter.replace).toHaveBeenCalledTimes(1);
    expect(mockRouter.replace).toHaveBeenCalledWith('/participant-invitations');
    expect(JSON.stringify(mockRouter.replace.mock.calls)).not.toContain(OLD_TOKEN);
    expect(JSON.stringify(mockRouter.push.mock.calls)).not.toContain(OLD_TOKEN);
    expect(requestsText()).not.toContain(OLD_TOKEN);
  });

  it('the old link leads to login with the fixed return_to only', async () => {
    mockParams = null;
    mockFetchRoutes([]);

    const router = renderRouter('src/app', { initialUrl: `/register?token=${OLD_TOKEN}` });

    // §9.2: logged out - the login screen, with the fixed return_to only.
    await waitFor(() => expect(screen.getByTestId('login-register-link')).toBeVisible());
    expect(router.getPathname()).toBe('/login');
    expect(router.getSearchParams()).toEqual(OLD_LINK_LOGIN_PARAMS);
    expect(router.getPathnameWithParams()).not.toContain(OLD_TOKEN);
    expect(requestsText()).not.toContain(OLD_TOKEN);
  });
});
