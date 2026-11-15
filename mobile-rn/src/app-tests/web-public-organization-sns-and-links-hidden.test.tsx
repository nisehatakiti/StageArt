import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * StageArt 団体管理UI整理 §7: the "反映されない" (absent) half - see
 * web-public-organization-sns-and-links-visible.test.tsx for the "ON"
 * half.
 */
describe('Web 団体公開ページ: ロゴ/SNS/外部リンクの反映（OFF）', () => {
  it('renders none of the logo/SNS/external-links sections when the Organization has none', async () => {
    mockFetchRoutes([
      {
        test: (u) => u.endsWith('/organizations/by-slug/no-profile-theatre'),
        status: 200,
        body: {
          id: 'org-2',
          name: 'シンプル劇団',
          slug: 'no-profile-theatre',
          description: null,
          logo_url: null,
          published_at: '2026-01-01T00:00:00+09:00',
          productions: [],
          sns: [],
          external_links: [],
        },
      },
    ]);

    renderRouter('src/app', { initialUrl: '/no-profile-theatre' });

    await waitFor(() => expect(screen.getByTestId('public-organization-content')).toBeVisible());

    expect(screen.queryByTestId('public-organization-logo')).toBeNull();
    expect(screen.queryByTestId('public-organization-sns')).toBeNull();
    expect(screen.queryByTestId('public-organization-external-links')).toBeNull();
  });
});
