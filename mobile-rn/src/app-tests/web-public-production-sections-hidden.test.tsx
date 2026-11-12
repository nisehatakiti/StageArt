import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

const publicProductionWithNoOptionalSections = {
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

/**
 * StageArt Web Completion Audit (Production公開ページ反映問題): the
 * "反映されない" half - see web-public-production-sections-visible.test.tsx
 * for the ON counterpart. When the Public API omits a section (its own
 * publish gate is off), the page must not render that section at all,
 * not merely leave it blank.
 */
describe('Web 公演公開ページ: 各公開設定セクションの反映（OFF）', () => {
  it('omits every optional section when the Public API returns null for it', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/by-slug/bare-play'), status: 200, body: publicProductionWithNoOptionalSections },
      { test: (u) => u.endsWith('/productions/prod-2/public-performances'), status: 200, body: [] },
      { test: (u) => u.endsWith('/productions/prod-2/public-tickets'), status: 200, body: { tickets: [] } },
    ]);

    renderRouter('src/app', { initialUrl: '/theatre-co/bare-play' });

    await waitFor(() => expect(screen.getByTestId('public-production-content')).toBeVisible());

    expect(screen.queryByTestId('public-production-flyer')).toBeNull();
    expect(screen.queryByTestId('public-production-description')).toBeNull();
    expect(screen.queryByTestId('public-production-venue')).toBeNull();
    expect(screen.queryByTestId('public-production-schedule-period')).toBeNull();
    expect(screen.queryByTestId('public-production-script-direction')).toBeNull();
  });
});
