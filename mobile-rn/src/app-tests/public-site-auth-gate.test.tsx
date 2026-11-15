import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

jest.mock('@/hooks/use-site-host', () => ({
  useSiteHostname: () => 'stageart.top',
  useIsPublicSite: () => true,
}));

/** 公開サイトのログイン導線指示書 §5.3: on a public-site host the (app)
 * group's unauthenticated gate never ends on a login form - its /login
 * redirect resolves back to the public top page. */
describe('Public site: authenticated-area URL while unauthenticated', () => {
  it('ends on the public landing page, not the login form', async () => {
    global.fetch = jest.fn();
    renderRouter('src/app', { initialUrl: '/home' });

    await waitFor(() => expect(screen.getByTestId('stageart-landing-headline')).toBeVisible());
    expect(screen.queryByTestId('login-email')).toBeNull();
  });
});
