import { StyleSheet } from 'react-native';
import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { Colors } from '@/constants/theme';
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
 * 公開ページ設定 not saved for this Organization: the public response has no
 * public_page and the page keeps its earlier look (app colors/fonts, no hero).
 */
describe('Web 団体公開ページ: 公開ページ設定なし', () => {
  it('keeps the earlier look when the organization has no saved settings (no public_page)', async () => {
    const { public_page: _unused, ...withoutSettings } = publicOrganization;
    mockFetchRoutes([{ test: (u) => u.endsWith('/organizations/by-slug/stageart-theatre'), status: 200, body: withoutSettings }]);

    renderRouter('src/app', { initialUrl: '/stageart-theatre' });

    await waitFor(() => expect(screen.getByTestId('public-organization-content')).toBeVisible());

    // The app's own colors (no theme background on the page, the app background on the content).
    expect(StyleSheet.flatten(screen.getByTestId('public-organization-page').props.style)?.backgroundColor).toBeUndefined();
    expect(StyleSheet.flatten(screen.getByTestId('public-organization-content').props.style)).toMatchObject({ backgroundColor: Colors.light.background });
    const name = StyleSheet.flatten(screen.getByTestId('public-organization-name').props.style);
    expect(name.color).toBe(Colors.light.text);
    expect(name.fontFamily).not.toBe(themeFontFamily('serif'));
    // No theme font on the body text (themeFontFamily('sans') is undefined on native, so check the style itself).
    expect(StyleSheet.flatten(screen.getByTestId('public-organization-description').props.style).fontFamily).toBeUndefined();
    expect(screen.queryByTestId('public-organization-hero')).toBeNull();
  });
});
