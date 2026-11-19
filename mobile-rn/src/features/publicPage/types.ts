/**
 * 公開ページ設定 (theme + hero of the Organization / Production public
 * pages). The preset lists themselves always come from the API
 * (PublicPageSettings.theme_presets / fonts / hero_presets, ported from
 * StageArtCore) - the app keeps no copy of them.
 */
export type PublicPageSite = 'organization' | 'production';

export type ThemeColorKey = 'bg' | 'surface' | 'text' | 'primary' | 'accent' | 'border';
export type ThemeFontKey = 'heading_font' | 'body_font';
export type ThemeFont = 'system' | 'sans' | 'serif';

export type PublicPageThemeValues = Record<ThemeColorKey, string> & {
  mode: 'light' | 'dark';
  heading_font: ThemeFont;
  body_font: ThemeFont;
};

export type ThemeSettings = {
  mode: 'preset' | 'custom';
  preset: string;
  custom: Record<ThemeColorKey, string> & Record<ThemeFontKey, ThemeFont>;
};

export type HeroSettings = {
  enabled: boolean;
  type: 'preset' | 'custom';
  preset: string | null;
  /** Set only by uploading an image (POST .../public-page/hero-image). */
  custom_image_url: string | null;
  title: string;
  description: string;
};

export type ThemePreset = PublicPageThemeValues & { key: string; label: string };

export type HeroPreset = {
  id: string;
  label: string;
  category: string;
  image_url: string;
  thumbnail_url: string;
};

/** GET/PUT /organizations/{id}/public-page, /productions/{id}/public-page */
export type PublicPageSettings = {
  site: PublicPageSite;
  theme: ThemeSettings;
  hero: HeroSettings;
  theme_presets: ThemePreset[];
  fonts: { key: ThemeFont; label: string }[];
  hero_presets: HeroPreset[];
};

/** `public_page` of the public Organization / Production responses. */
export type PublicPageAppearance = {
  theme: PublicPageThemeValues;
  hero: { image_url: string; title: string; description: string } | null;
};
