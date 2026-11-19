import { createContext, useContext, type PropsWithChildren } from 'react';
import { Platform } from 'react-native';

import { Fonts } from '@/constants/theme';

import type { PublicPageThemeValues, ThemeFont } from './types';

/**
 * Applies a public page's theme (StageArtCore's ThemeRenderer: background,
 * surface, text, primary, accent, border, heading/body font) to everything
 * rendered inside <PublicPageThemeProvider>. Outside of it - every other
 * screen, and the AppShell chrome around the public pages - nothing
 * changes: useTheme() / ThemedText only switch when a theme is provided.
 */
const PublicPageThemeContext = createContext<PublicPageThemeValues | null>(null);

export function PublicPageThemeProvider({ theme, children }: PropsWithChildren<{ theme: PublicPageThemeValues | null | undefined }>) {
  return <PublicPageThemeContext.Provider value={theme ?? null}>{children}</PublicPageThemeContext.Provider>;
}

export function usePublicPageTheme(): PublicPageThemeValues | null {
  return useContext(PublicPageThemeContext);
}

/** StageArtCore ThemeRenderer's font stacks (system and sans share one stack there). */
const WEB_FONT_STACKS: Record<ThemeFont, string> = {
  system: '-apple-system, BlinkMacSystemFont, "Noto Sans JP", "Yu Gothic", sans-serif',
  sans: '-apple-system, BlinkMacSystemFont, "Noto Sans JP", "Yu Gothic", sans-serif',
  serif: 'Georgia, "Times New Roman", "Noto Serif JP", serif',
};

export function themeFontFamily(font: ThemeFont): string | undefined {
  if (Platform.OS === 'web') {
    return WEB_FONT_STACKS[font] ?? WEB_FONT_STACKS.system;
  }

  return font === 'serif' ? Fonts.serif : undefined;
}

/** `--stageart-muted` of ThemeRenderer: the text color at 62%. */
export function mutedColor(hex: string): string {
  const value = hex.replace('#', '');
  const full = value.length === 3 ? value.split('').map((c) => c + c).join('') : value;
  const r = parseInt(full.slice(0, 2), 16);
  const g = parseInt(full.slice(2, 4), 16);
  const b = parseInt(full.slice(4, 6), 16);

  return Number.isNaN(r + g + b) ? hex : `rgba(${r}, ${g}, ${b}, 0.62)`;
}

/**
 * The app color tokens (Colors.light's keys) a themed public page uses,
 * so ThemedText / ThemedView pick the page's colors up unchanged.
 */
export function publicPageAppColors(theme: PublicPageThemeValues) {
  return {
    text: theme.text,
    background: theme.bg,
    backgroundElement: theme.surface,
    backgroundSelected: theme.border,
    textSecondary: mutedColor(theme.text),
    accent: theme.accent,
    accentSoft: theme.surface,
    accentSecondary: theme.accent,
  };
}
