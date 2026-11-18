import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * PR #7 R14-B (design doc §7.1 「POST /auth/email/register の
 * invitation_token」, §10, §14.2; rewritten for the spec change - previously
 * a registration from an invitation link forwarded the token and skipped
 * the mail confirmation): registering after opening an old invitation link
 * is an ordinary registration. No invitation_token is sent, the address is
 * the one the person typed, and the usual confirmation mail step follows
 * (registration-pending) - nobody becomes a member by registering.
 */
describe('register screen: registration from an old invitation link', () => {
  it('sends no invitation token and goes to the ordinary mail confirmation', async () => {
    mockFetchRoutes([
      {
        test: (u) => u.endsWith('/auth/email/register'),
        status: 201,
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
      {
        test: (u) => u.endsWith('/me'),
        status: 200,
        body: { id: 'person-1', word_press_user_id: 1, email_verified: false, family_name: null, given_name: null },
      },
    ]);

    const router = renderRouter('src/app', { initialUrl: '/register?token=good-token' });

    await waitFor(() => expect(screen.getByTestId('register-email')).toBeVisible());
    await waitFor(() => expect(router.getSearchParams()).toEqual({}));
    expect(screen.getByTestId('register-email').props.value).toBe('');

    await fireEvent.changeText(screen.getByTestId('register-email'), 'invitee@example.com');
    await fireEvent.changeText(screen.getByTestId('register-password'), 'password123');
    await fireEvent.press(screen.getByTestId('register-submit'));

    await waitFor(() => expect(router.getPathname()).toBe('/registration-pending'));

    const registerCall = (global.fetch as jest.Mock).mock.calls.find(([url]) => String(url).endsWith('/auth/email/register'));
    expect(registerCall).toBeDefined();
    const body = JSON.parse(registerCall![1].body as string);
    // The typed address itself is not asserted: this harness does not reflect typing on this
    // screen (register-flow.test.tsx does not assert it either). The empty initial address is
    // checked in register-invitation-token-valid.test.tsx.
    expect(body).not.toHaveProperty('invitation_token');
  });
});
