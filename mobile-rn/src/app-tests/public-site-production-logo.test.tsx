import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

jest.mock('@/hooks/use-site-host', () => ({
  useSiteHostname: () => 'www.stageart.top',
  useIsPublicSite: () => true,
}));

const publicProduction = {
  id: 'prod-2',
  name: '秋の公演',
  slug: 'bare-play',
  title_heading: null,
  published_at: '2026-01-01T00:00:00+09:00',
  description: null,
  flyer_url: null,
  venue_name: null,
  schedule_start_date: null,
  schedule_end_date: null,
  script_credit: null,
  direction_credit: null,
  organization: { id: 'org-1', name: '劇団サンプル', slug: 'theatre-co' },
};

/** 公開サイトのログイン導線指示書 §5.1: on a public-site host the
 * production page's StageArt logo returns to the public top page. */
describe('Public site: production page logo', () => {
  it('returns to the public landing page without showing the login form', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/by-slug/bare-play'), status: 200, body: publicProduction },
      { test: (u) => u.endsWith('/productions/prod-2/public-performances'), status: 200, body: [] },
      { test: (u) => u.endsWith('/productions/prod-2/public-tickets'), status: 200, body: { tickets: [] } },
    ]);

    renderRouter('src/app', { initialUrl: '/theatre-co/bare-play' });

    await waitFor(() => expect(screen.getByTestId('public-production-content')).toBeVisible());
    expect(screen.queryByTestId('web-bottom-nav')).toBeNull();

    fireEvent.press(screen.getByTestId('app-shell-logo'));

    await waitFor(() => expect(screen.getByTestId('stageart-landing-headline')).toBeVisible());
    expect(screen.queryByTestId('login-email')).toBeNull();
  });
});
