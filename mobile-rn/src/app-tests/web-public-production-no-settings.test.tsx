import { StyleSheet } from 'react-native';
import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { Colors } from '@/constants/theme';

import { mockFetchRoutes } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

const publicProduction = {
  id: 'prod-1',
  name: '秋の公演',
  slug: 'autumn-play',
  title_heading: null,
  published_at: '2026-01-01T00:00:00+09:00',
  description: '心温まる家族劇です。',
  flyer_url: null,
  venue_name: null,
  schedule_start_date: null,
  schedule_end_date: null,
  script_credit: null,
  direction_credit: null,
  organization: { id: 'org-1', name: '劇団サンプル', slug: 'theatre-co' },
  public_page: {
    theme: {
      mode: 'light', bg: '#faf8f3', surface: '#ffffff', text: '#24211e', primary: '#2f2925', accent: '#a54f3d', border: '#ded5c8',
      heading_font: 'serif', body_font: 'sans',
    },
    hero: { image_url: 'https://api.test/hero/production/production-hero-01-sky.jpg', title: '秋の特別公演', description: '三年ぶりの再演' },
  },
};

/**
 * 公開ページ設定 not saved for this Production: the public response has no
 * public_page and the page keeps its earlier look (app colors/fonts, no hero).
 */
describe('Web 公演公開ページ: 公開ページ設定なし', () => {
  it('keeps the earlier look when the production has no saved settings (no public_page)', async () => {
    const { public_page: _unused, ...withoutSettings } = publicProduction;
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/by-slug/autumn-play'), status: 200, body: withoutSettings },
      { test: (u) => u.endsWith('/productions/prod-1/public-performances'), status: 200, body: [] },
      { test: (u) => u.endsWith('/productions/prod-1/public-tickets'), status: 200, body: { tickets: [] } },
    ]);

    renderRouter('src/app', { initialUrl: '/theatre-co/autumn-play' });

    await waitFor(() => expect(screen.getByTestId('public-production-content')).toBeVisible());

    expect(StyleSheet.flatten(screen.getByTestId('public-production-page').props.style)?.backgroundColor).toBeUndefined();
    expect(StyleSheet.flatten(screen.getByTestId('public-production-name').props.style)).toMatchObject({ color: Colors.light.text });
    expect(screen.queryByTestId('public-production-hero')).toBeNull();
    expect(screen.getByTestId('public-production-name')).toHaveTextContent('秋の公演');
  });
});
