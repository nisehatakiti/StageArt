import type { PublicPageSettings, PublicPageSite, ThemePreset } from '@/features/publicPage/types';

/**
 * API response fixtures for 公開ページ設定. The preset values below are a
 * subset of what the plugin returns (ported from StageArtCore's
 * Theme::presets() and hero manifests); the app itself never holds them.
 */
const lightStandard: ThemePreset = {
  key: 'light_standard', label: 'ライト・スタンダード', mode: 'light',
  bg: '#f7f5f0', surface: '#ffffff', text: '#242424', primary: '#333333', accent: '#9a6b2f', border: '#ddd7cc',
  heading_font: 'system', body_font: 'system',
};
const darkTheater: ThemePreset = {
  key: 'dark_theater', label: 'ダーク・シアター', mode: 'dark',
  bg: '#111114', surface: '#1b1b20', text: '#f0edf0', primary: '#f4f0e8', accent: '#b68b57', border: '#36343a',
  heading_font: 'serif', body_font: 'sans',
};
const darkDrama: ThemePreset = {
  key: 'dark_drama', label: 'ダーク・ドラマ', mode: 'dark',
  bg: '#0d0d0f', surface: '#19191d', text: '#eee9e1', primary: '#f4eee5', accent: '#a66d55', border: '#353136',
  heading_font: 'serif', body_font: 'sans',
};

export function publicPageSettingsFixture(site: PublicPageSite, overrides: Partial<PublicPageSettings> = {}): PublicPageSettings {
  return {
    site,
    theme: {
      mode: 'preset',
      preset: 'light_standard',
      custom: { bg: '#f7f5f0', surface: '#ffffff', text: '#242424', primary: '#333333', accent: '#9a6b2f', border: '#ddd7cc', heading_font: 'system', body_font: 'system' },
    },
    hero: { enabled: false, type: 'preset', preset: null, custom_image_url: null, title: '', description: '' },
    theme_presets: site === 'production' ? [lightStandard, darkTheater, darkDrama] : [lightStandard, darkTheater],
    fonts: [
      { key: 'system', label: 'システム標準' },
      { key: 'sans', label: 'ゴシック系' },
      { key: 'serif', label: '明朝・セリフ系' },
    ],
    hero_presets:
      site === 'production'
        ? [
            { id: 'sky-blue', label: '青空', category: '空', image_url: 'https://api.test/hero/production/production-hero-01-sky.jpg', thumbnail_url: 'https://api.test/hero/production/thumbs/production-hero-01-sky.jpg' },
            { id: 'sea-blue', label: '青い海', category: '海', image_url: 'https://api.test/hero/production/production-hero-05-blue-sea.jpg', thumbnail_url: 'https://api.test/hero/production/thumbs/production-hero-05-blue-sea.jpg' },
          ]
        : [
            { id: 'circle-shadows', label: '輪になる影', category: 'シルエット・影', image_url: 'https://api.test/hero/organization/circle-shadows.jpg', thumbnail_url: 'https://api.test/hero/organization/thumbs/circle-shadows.jpg' },
            { id: 'confetti', label: '紙吹雪', category: 'ポップ・グラフィック', image_url: 'https://api.test/hero/organization/confetti.jpg', thumbnail_url: 'https://api.test/hero/organization/thumbs/confetti.jpg' },
          ],
    ...overrides,
  };
}

/**
 * Wraps the installed route-matcher fetch: the public-page endpoint
 * answers GET with `current`, PUT with the sent settings (recorded in
 * `puts`), and the hero-image upload with `current` plus an uploaded URL.
 */
export function mockPublicPageEndpoint(path: string, initial: PublicPageSettings, uploadedUrl = 'https://api.test/wp-content/uploads/2026/10/own-hero.jpg') {
  const state = { current: initial, puts: [] as unknown[] };
  const installedFetch = global.fetch;

  global.fetch = jest.fn(async (input: unknown, init?: RequestInit) => {
    const url = String(input);
    const respond = (body: unknown) =>
      ({ ok: true, status: 200, text: async () => JSON.stringify(body), json: async () => body, blob: async () => new Blob(['x'], { type: 'image/jpeg' }) }) as unknown as Response;

    if (url.startsWith('file://')) {
      return respond({});
    }

    if (url.endsWith(`${path}/hero-image`)) {
      state.current = { ...state.current, hero: { ...state.current.hero, custom_image_url: uploadedUrl } };
      return respond(state.current);
    }

    if (url.endsWith(path)) {
      if (init?.method === 'PUT') {
        const sent = JSON.parse(String(init.body));
        state.puts.push(sent);
        state.current = { ...state.current, theme: sent.theme, hero: { ...sent.hero, custom_image_url: state.current.hero.custom_image_url } };
      }
      return respond(state.current);
    }

    return installedFetch(input as RequestInfo, init);
  }) as unknown as typeof fetch;

  return state;
}
