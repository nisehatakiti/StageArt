import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * A real-device correction: "違うメールアドレスの場合" no longer goes
 * through the full useLogout() sequence (there is no real session to
 * sign out of on this screen anymore - see registration-pending.tsx's
 * own docblock) - it just clears the transient token and navigates
 * straight to /login, so no network mock is needed here at all.
 */
describe('registration-pending screen: wrong email', () => {
  it('returns to /login', async () => {
    renderRouter('src/app', { initialUrl: '/registration-pending?email=person%40example.com' });

    await waitFor(() => expect(screen.getByTestId('registration-pending-wrong-email')).toBeVisible());
    await fireEvent.press(screen.getByTestId('registration-pending-wrong-email'));

    await waitFor(() => expect(screen.getByTestId('login-email')).toBeVisible());
  });
});
