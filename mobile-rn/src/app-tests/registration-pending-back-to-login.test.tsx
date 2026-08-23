import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * Kept in its own file - see login-flow.test.tsx's docblock for why
 * every renderRouter() call needs a dedicated file in this codebase.
 *
 * A real-device follow-up: a user who reaches registration-pending via
 * a login attempt (not registration) must always have a guaranteed way
 * back to /login, distinct from "メールアドレスが間違っている場合" (see
 * registration-pending.tsx's own docblock for why the two are kept
 * separate).
 */
describe('registration-pending screen: back to login', () => {
  it('returns to /login', async () => {
    renderRouter('src/app', { initialUrl: '/registration-pending?email=person%40example.com' });

    await waitFor(() => expect(screen.getByTestId('registration-pending-back-to-login')).toBeVisible());
    await fireEvent.press(screen.getByTestId('registration-pending-back-to-login'));

    await waitFor(() => expect(screen.getByTestId('login-email')).toBeVisible());
  });
});
