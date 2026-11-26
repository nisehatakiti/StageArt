import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

jest.mock('@/hooks/use-site-host', () => ({
  useSiteHostname: () => 'app.stageart.top',
  useIsPublicSite: () => false,
}));

/** 公開サイトのログイン導線指示書 §2.3: app.stageart.top keeps the login
 * screen at /login. */
describe('Application host (app): /login', () => {
  it('shows the login form', async () => {
    global.fetch = jest.fn();
    renderRouter('src/app', { initialUrl: '/login' });

    await waitFor(() => expect(screen.getByTestId('login-email')).toBeVisible());
    expect(screen.getByTestId('login-next')).toBeVisible();
    // Step 1 is the address only (login step 2 comes after POST /auth/account/check).
    expect(screen.queryByTestId('login-password')).toBeNull();
  });
});
