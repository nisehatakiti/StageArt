import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

jest.mock('@/hooks/use-site-host', () => ({
  useSiteHostname: () => 'dummy.stageart.top',
  useIsPublicSite: () => true,
}));

const publicOrganization = {
  id: 'org-1',
  name: 'StageArt劇団',
  slug: 'stageart-theatre',
  description: null,
  logo_url: null,
  published_at: '2026-01-01T00:00:00+09:00',
  productions: [],
  sns: [],
  external_links: [],
};

/** 公開サイトのログイン導線指示書 §5.1/§2.2: on a public-site host the
 * organization page offers no login entry (no "ログインしてフォロー", no
 * Bottom Navigation into the authenticated area), and its StageArt logo
 * returns to the public top page instead of /home → /login. */
describe('Public site: organization page logo', () => {
  it('has no login entry and its logo returns to the public landing page', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations/by-slug/stageart-theatre'), status: 200, body: publicOrganization },
    ]);

    renderRouter('src/app', { initialUrl: '/stageart-theatre' });

    await waitFor(() => expect(screen.getByTestId('public-organization-content')).toBeVisible());
    expect(screen.queryByTestId('public-organization-login-to-follow')).toBeNull();
    expect(screen.queryByTestId('web-bottom-nav')).toBeNull();
    expect(screen.getByTestId('app-shell-logo')).toHaveProp('accessibilityLabel', 'StageArt トップページへ戻る');

    fireEvent.press(screen.getByTestId('app-shell-logo'));

    await waitFor(() => expect(screen.getByTestId('stageart-landing-headline')).toBeVisible());
    expect(screen.queryByTestId('login-email')).toBeNull();
  });
});
