import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/** Kept in its own file - see login-flow.test.tsx's docblock. */
describe('Forgot password screen', () => {
  it('shows the email input for requesting a password reset', async () => {
    renderRouter('src/app', { initialUrl: '/forgot-password' });

    await waitFor(() => expect(screen.getByTestId('forgot-password-email')).toBeVisible());
    expect(screen.getByTestId('forgot-password-submit')).toBeVisible();
  });
});
