import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/** Kept in its own file - see login-flow.test.tsx's docblock. */
describe('Reset password screen', () => {
  it('shows the token and new password inputs', async () => {
    renderRouter('src/app', { initialUrl: '/reset-password' });

    await waitFor(() => expect(screen.getByTestId('reset-password-token')).toBeVisible());
    expect(screen.getByTestId('reset-password-new-password')).toBeVisible();
    expect(screen.getByTestId('reset-password-submit')).toBeVisible();
  });
});
