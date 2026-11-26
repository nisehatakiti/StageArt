import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

const OLD_TOKEN = 'old-invitation-token-123';

/** Every URL requested and the request bodies, to check the old token never appears in them. */
function requestsText(): string {
  return (global.fetch as jest.Mock).mock.calls.map(([url, init]: [string, RequestInit?]) => `${String(url)} ${String(init?.body ?? '')}`).join('\n');
}

const loginRoute = {
  test: (u: string) => u.endsWith('/auth/email/login'),
  status: 200,
  body: { access_token: 'access-token-1', refresh_token: 'refresh-token-1', token_type: 'Bearer', expires_in: 3600 },
};

type Router = ReturnType<typeof renderRouter>;

// The renderRouter() result is not returned from an async function: in this testing library it
// is thenable, so awaiting it would unwrap it.
async function loginAfterOpeningOldLink(router: Router) {

  // §9.2: logged out - the login screen, with the fixed return_to only.
  await waitFor(() => expect(screen.getByTestId('login-register-link')).toBeVisible());
  expect(router.getPathname()).toBe('/login');
  expect(router.getSearchParams()).toEqual({ return_to: '/participant-invitations' });
  expect(router.getPathnameWithParams()).not.toContain(OLD_TOKEN);

  await fireEvent.changeText(screen.getByTestId('login-email'), 'member@example.com');
  await fireEvent.changeText(screen.getByTestId('login-password'), 'password123');
  await fireEvent.press(screen.getByTestId('login-submit'));
}

/**
 * PR #7 R14-B (design doc §9.2 / §10; approved final fix): an old invitation
 * link `/register?token=...` opened while logged out leads, through the
 * real router, to login with return_to=/participant-invitations and then
 * to the invitation list - via the name screen (carrying the same
 * return_to) when the person has no name yet. The old token is never read:
 * it appears in no URL, no navigation argument and no request. (Saving the
 * name and continuing to the list: r14b-set-name-return-to.test.tsx.)
 *
 * The case with a name is in r14b-old-invitation-link-login.test.tsx (one
 * full-router render per file).
 */
describe('R14-B 古い招待リンク: ログイン後の遷移（名前未設定）', () => {
  it('a person without a name goes to the name screen carrying return_to', async () => {
    mockFetchRoutes([
      loginRoute,
      {
        test: (u) => u.endsWith('/me'),
        status: 200,
        body: { id: 'person-1', word_press_user_id: 1, email_verified: true, family_name: null, given_name: null },
      },
    ]);

    const router = renderRouter('src/app', { initialUrl: `/register?token=${OLD_TOKEN}` });
    await loginAfterOpeningOldLink(router);

    await waitFor(() => expect(router.getPathname()).toBe('/set-name'));
    expect(router.getSearchParams()).toEqual({ return_to: '/participant-invitations' });
    expect(router.getPathnameWithParams()).not.toContain(OLD_TOKEN);
    expect(screen.getByTestId('set-name-submit')).toBeVisible();
    expect(requestsText()).not.toContain(OLD_TOKEN);
  });
});
