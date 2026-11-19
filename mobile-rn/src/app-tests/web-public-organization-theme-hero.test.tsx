import { StyleSheet } from 'react-native';
import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { themeFontFamily } from '@/features/publicPage/PublicPageTheme';

import { mockFetchRoutes } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

const darkTheater = {
  mode: 'dark', bg: '#111114', surface: '#1b1b20', text: '#f0edf0', primary: '#f4f0e8', accent: '#b68b57', border: '#36343a',
  heading_font: 'serif', body_font: 'sans',
};

const publicOrganization = {
  id: 'org-1',
  name: 'StageArt劇団',
  slug: 'stageart-theatre',
  description: '舞台芸術を探求する劇団です。',
  logo_url: null,
  published_at: '2026-01-01T00:00:00+09:00',
  productions: [],
  sns: [],
  external_links: [],
  public_page: {
    theme: darkTheater,
    hero: { image_url: 'https://api.test/hero/organization/confetti.jpg', title: '', description: '' },
  },
};

/**
 * 公開ページ設定 is applied on the public organization page: the saved
 * theme colors/fonts (page background, heading color + font) and the hero
 * (cover background, empty title -> the organization name).
 */
describe('Web 団体公開ページ: 公開ページ設定の反映', () => {
  it('applies the saved theme and hero', async () => {
    mockFetchRoutes([{ test: (u) => u.endsWith('/organizations/by-slug/stageart-theatre'), status: 200, body: publicOrganization }]);

    renderRouter('src/app', { initialUrl: '/stageart-theatre' });

    await waitFor(() => expect(screen.getByTestId('public-organization-content')).toBeVisible());

    expect(StyleSheet.flatten(screen.getByTestId('public-organization-page').props.style)).toMatchObject({ backgroundColor: '#111114' });
    expect(StyleSheet.flatten(screen.getByTestId('public-organization-content').props.style)).toMatchObject({ backgroundColor: '#111114' });

    const name = StyleSheet.flatten(screen.getByTestId('public-organization-name').props.style);
    expect(name.color).toBe('#f4f0e8');
    // Heading font of the theme (the web font stacks are covered by PublicPageTheme.test.ts).
    expect(name.fontFamily).toBe(themeFontFamily('serif'));
    const description = StyleSheet.flatten(screen.getByTestId('public-organization-description').props.style);
    expect(description.color).toBe('#f0edf0');
    expect(description.fontFamily).toBe(themeFontFamily('sans'));

    expect(screen.getByTestId('public-organization-hero')).toBeVisible();
    expect(screen.getByTestId('public-organization-hero-title')).toHaveTextContent('StageArt劇団');
    expect(screen.getByTestId('public-organization-hero-description')).toHaveTextContent('舞台芸術を探求する劇団です。');
    expect(screen.getByTestId('public-organization-hero-image').props.source).toMatchObject({
      uri: 'https://api.test/hero/organization/confetti.jpg',
    });
    // cover + centered, as StageArtCore applies hero backgrounds (never used for the logo).
    expect(screen.getByTestId('public-organization-hero-image').props.resizeMode).toBe('cover');
  });
});
