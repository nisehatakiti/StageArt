import { StyleSheet } from 'react-native';
import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

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

/** 公開ページ設定 is applied on the public production page with the production's own theme and hero. */
describe('Web 公演公開ページ: 公開ページ設定の反映', () => {
  it('applies the production theme and hero', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/by-slug/autumn-play'), status: 200, body: publicProduction },
      { test: (u) => u.endsWith('/productions/prod-1/public-performances'), status: 200, body: [] },
      { test: (u) => u.endsWith('/productions/prod-1/public-tickets'), status: 200, body: { tickets: [] } },
    ]);

    renderRouter('src/app', { initialUrl: '/theatre-co/autumn-play' });

    await waitFor(() => expect(screen.getByTestId('public-production-content')).toBeVisible());

    expect(StyleSheet.flatten(screen.getByTestId('public-production-page').props.style)).toMatchObject({ backgroundColor: '#faf8f3' });
    expect(StyleSheet.flatten(screen.getByTestId('public-production-name').props.style)).toMatchObject({ color: '#2f2925' });
    expect(screen.getByTestId('public-production-hero-title')).toHaveTextContent('秋の特別公演');
    expect(screen.getByTestId('public-production-hero-description')).toHaveTextContent('三年ぶりの再演');
    // The page's own content stays unchanged.
    expect(screen.getByTestId('public-production-name')).toHaveTextContent('秋の公演');
    expect(screen.getByTestId('public-production-description')).toHaveTextContent('心温まる家族劇です。');
  });
});
