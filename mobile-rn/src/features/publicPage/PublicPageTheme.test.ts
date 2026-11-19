import { Platform } from 'react-native';

import { mutedColor, publicPageAppColors, themeFontFamily } from './PublicPageTheme';

const darkTheater = {
  mode: 'dark' as const, bg: '#111114', surface: '#1b1b20', text: '#f0edf0', primary: '#f4f0e8', accent: '#b68b57', border: '#36343a',
  heading_font: 'serif' as const, body_font: 'sans' as const,
};

describe('PublicPageTheme', () => {
  const originalOS = Platform.OS;
  afterEach(() => {
    Platform.OS = originalOS;
  });

  it('uses StageArtCore ThemeRenderer font stacks on the web', () => {
    Platform.OS = 'web';

    expect(themeFontFamily('serif')).toBe('Georgia, "Times New Roman", "Noto Serif JP", serif');
    expect(themeFontFamily('sans')).toBe('-apple-system, BlinkMacSystemFont, "Noto Sans JP", "Yu Gothic", sans-serif');
    expect(themeFontFamily('system')).toBe(themeFontFamily('sans'));
  });

  it('maps the theme to the app color tokens, muted text at 62%', () => {
    expect(mutedColor('#f0edf0')).toBe('rgba(240, 237, 240, 0.62)');
    expect(mutedColor('#fff')).toBe('rgba(255, 255, 255, 0.62)');
    expect(publicPageAppColors(darkTheater)).toMatchObject({
      text: '#f0edf0',
      background: '#111114',
      backgroundElement: '#1b1b20',
      backgroundSelected: '#36343a',
      accent: '#b68b57',
    });
  });
});
