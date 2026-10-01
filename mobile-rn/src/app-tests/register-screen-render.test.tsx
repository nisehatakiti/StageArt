import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/** Kept in its own file - see login-flow.test.tsx's docblock. */
describe('Register screen', () => {
  it('shows the Email+Password registration fields, with no WordPress terms', async () => {
    renderRouter('src/app', { initialUrl: '/register' });

    await waitFor(() => expect(screen.getByTestId('register-email')).toBeVisible());
    expect(screen.getByTestId('register-password')).toBeVisible();
    expect(screen.getByTestId('register-submit')).toBeVisible();
    expect(screen.queryByText(/WordPress/i)).toBeNull();

    // StageArt 招待登録のメール確認省略ラウンド: only an invitation-sourced
    // registration fixes the email field - an ordinary direct /register
    // visit (no token) keeps it editable.
    expect(screen.getByTestId('register-email').props.editable).not.toBe(false);
  });
});
