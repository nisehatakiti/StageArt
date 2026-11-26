import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

jest.mock('@/hooks/use-site-host', () => ({
  useSiteHostname: () => 'app.stageart.top',
  useIsPublicSite: () => false,
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

/** 公開サイトのログイン導線指示書 §2.3: on an application host the public
 * production page keeps its existing logo behavior - /home, whose
 * authenticated gate shows the login form to an unauthenticated visitor. */
describe('Application host: production page logo', () => {
  it('keeps going to /home, which shows the login form while unauthenticated', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/by-slug/bare-play'), status: 200, body: publicProduction },
      { test: (u) => u.endsWith('/productions/prod-2/public-performances'), status: 200, body: [] },
      { test: (u) => u.endsWith('/productions/prod-2/public-tickets'), status: 200, body: { tickets: [] } },
    ]);

    renderRouter('src/app', { initialUrl: '/theatre-co/bare-play' });

    await waitFor(() => expect(screen.getByTestId('public-production-content')).toBeVisible());
    expect(screen.getByTestId('app-shell-logo')).toHaveProp('accessibilityLabel', 'StageArt ホームへ戻る');

    fireEvent.press(screen.getByTestId('app-shell-logo'));

    await waitFor(() => expect(screen.getByTestId('login-email')).toBeVisible());
    expect(screen.queryByTestId('stageart-landing-headline')).toBeNull();
  });
});
