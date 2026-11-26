import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

// The public-site build (EXPO_PUBLIC_STAGEART_SITE=public); the host hook
// itself is real, so this is the build flag alone deciding.
jest.mock('@/config/site-build', () => ({ IS_PUBLIC_SITE_BUILD: true }));

/** Public-site build: an authentication screen renders no form and
 * returns to the public top page. */
describe('Public-site build: /register', () => {
  it('shows the public landing page, never the registration form', async () => {
    global.fetch = jest.fn();
    renderRouter('src/app', { initialUrl: '/register' });

    await waitFor(() => expect(screen.getByTestId('stageart-landing-headline')).toBeVisible());
    expect(screen.queryByTestId('register-email')).toBeNull();
    expect(screen.queryByTestId('register-password')).toBeNull();
  });
});
