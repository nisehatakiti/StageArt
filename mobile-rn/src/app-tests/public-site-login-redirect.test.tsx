import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

jest.mock('@/hooks/use-site-host', () => ({
  useSiteHostname: () => 'dummy.stageart.top',
  useIsPublicSite: () => true,
}));

/** 公開サイトのログイン導線指示書 §5.2: opening /login directly on a
 * public-site host returns to the public top page instead of showing the
 * login form. */
describe('Public site: /login', () => {
  it('redirects to the public landing page without rendering the login form', async () => {
    global.fetch = jest.fn();
    renderRouter('src/app', { initialUrl: '/login' });

    await waitFor(() => expect(screen.getByTestId('stageart-landing-headline')).toBeVisible());
    expect(screen.queryByTestId('login-email')).toBeNull();
    expect(screen.queryByTestId('login-submit')).toBeNull();
    expect(screen.queryByTestId('login-google-button')).toBeNull();
  });
});
