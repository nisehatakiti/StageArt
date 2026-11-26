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

/** 公開サイトのログイン導線指示書 §2.2: the public-site root shows the
 * feature landing page, never the login form. Kept in its own file per
 * this codebase's one-renderRouter()-per-file convention (see
 * login-flow.test.tsx). */
describe('Public site: /', () => {
  it('shows the public landing page and no login form', async () => {
    global.fetch = jest.fn();
    renderRouter('src/app', { initialUrl: '/' });

    await waitFor(() => expect(screen.getByTestId('stageart-landing-headline')).toBeVisible());
    expect(screen.queryByTestId('login-email')).toBeNull();
    expect(screen.queryByTestId('login-password')).toBeNull();
    expect(global.fetch).not.toHaveBeenCalled();
  });
});
