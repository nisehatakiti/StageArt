import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

const publicProductionWithAllSections = {
  id: 'prod-1',
  name: '秋の公演',
  slug: 'autumn-play',
  title_heading: null,
  published_at: '2026-01-01T00:00:00+09:00',
  description: '心温まる家族劇です。',
  flyer_url: 'https://example.com/flyer.jpg',
  venue_name: '中央劇場',
  schedule_start_date: '2026-11-01',
  schedule_end_date: '2026-11-10',
  script_credit: '脚本太郎',
  direction_credit: '演出花子',
  organization: { id: 'org-1', name: '劇団サンプル', slug: 'theatre-co' },
};

/**
 * StageArt Web Completion Audit (Production公開ページ反映問題): confirms
 * each per-section field the Public API can now return (description/
 * flyer(Hero)/venue/schedule period/script&direction credit) actually
 * renders on the public page when the Public API returns it - the
 * "反映される" half of this audit's report. See
 * web-public-production-sections-hidden.test.tsx for the "反映されない"
 * (null) half - split per this codebase's one-it()-per-file convention
 * for renderRouter-based tests.
 */
describe('Web 公演公開ページ: 各公開設定セクションの反映（ON）', () => {
  it('renders flyer(Hero)/description/venue/schedule/credits when the Public API returns them', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/by-slug/autumn-play'), status: 200, body: publicProductionWithAllSections },
      { test: (u) => u.endsWith('/productions/prod-1/public-performances'), status: 200, body: [] },
      { test: (u) => u.endsWith('/productions/prod-1/public-tickets'), status: 200, body: { tickets: [] } },
    ]);

    renderRouter('src/app', { initialUrl: '/theatre-co/autumn-play' });

    await waitFor(() => expect(screen.getByTestId('public-production-content')).toBeVisible());

    expect(screen.getByTestId('public-production-flyer')).toBeVisible();
    expect(screen.getByTestId('public-production-description')).toHaveTextContent('心温まる家族劇です。');
    expect(screen.getByTestId('public-production-venue')).toHaveTextContent('会場: 中央劇場');
    expect(screen.getByTestId('public-production-schedule-period')).toHaveTextContent('2026-11-01', { exact: false });
    expect(screen.getByTestId('public-production-schedule-period')).toHaveTextContent('2026-11-10', { exact: false });
    expect(screen.getByTestId('public-production-script-direction')).toHaveTextContent('脚本太郎', { exact: false });
    expect(screen.getByTestId('public-production-script-direction')).toHaveTextContent('演出花子', { exact: false });
  });
});
