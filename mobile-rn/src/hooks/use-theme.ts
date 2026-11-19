/**
 * Learn more about light and dark modes:
 * https://docs.expo.dev/guides/color-schemes/
 */

import { Colors } from '@/constants/theme';
import { publicPageAppColors, usePublicPageTheme } from '@/features/publicPage/PublicPageTheme';
import { useColorScheme } from '@/hooks/use-color-scheme';

export function useTheme() {
  const scheme = useColorScheme();
  const publicPageTheme = usePublicPageTheme();
  const theme = scheme === 'unspecified' ? 'light' : scheme;

  // Inside a public page with its own theme (公開ページ設定), that theme
  // wins over the OS light/dark scheme.
  if (publicPageTheme) {
    return publicPageAppColors(publicPageTheme);
  }

  return Colors[theme];
}
