import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

describe('navigation (Expo Router)', () => {
  beforeEach(() => {
    global.fetch = jest.fn();
  });

  it('redirects an unauthenticated user from / to /login, which shows the login form', async () => {
    renderRouter('src/app', { initialUrl: '/' });

    await waitFor(() => expect(screen.getByTestId('login-email')).toBeVisible());
    expect(screen.getByTestId('login-next')).toBeVisible();
    // Step 1 is the address only (login step 2 comes after POST /auth/account/check).
    expect(screen.queryByTestId('login-password')).toBeNull();
  });
});
