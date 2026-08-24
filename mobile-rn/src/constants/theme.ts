/**
 * Below are the colors that are used in the app. The colors are defined in the light and dark mode.
 * There are many other ways to style your app. For example, [Nativewind](https://www.nativewind.dev/), [Tamagui](https://tamagui.dev/), [unistyles](https://reactnativeunistyles.vercel.app), etc.
 */

import '@/global.css';

import { Platform } from 'react-native';

/**
 * mobile-rn Blueprint v1.5 alignment Phase (BusinessFlowUX.md §11
 * "モバイルVisual Design方針"): `accent`/`accentSoft`/`accentSecondary` are
 * new, additive tokens toward the Blueprint's "ポップ・親しみやすい・小劇場
 * らしい手作り感・少し泥臭い雰囲気" direction (赤 as the primary accent,
 * 黄色 as a secondary highlight) - existing tokens (text/background/
 * backgroundElement/backgroundSelected/textSecondary) are left exactly as
 * they were so no existing screen's styling is disturbed by this change;
 * new screens (Person Home and beyond) opt in to the new tokens
 * incrementally.
 */
export const Colors = {
  light: {
    text: '#000000',
    background: '#ffffff',
    backgroundElement: '#F0F0F3',
    backgroundSelected: '#E0E1E6',
    textSecondary: '#60646C',
    accent: '#C4432F',
    accentSoft: '#F7E4DE',
    accentSecondary: '#E0A02E',
  },
  dark: {
    text: '#ffffff',
    background: '#000000',
    backgroundElement: '#212225',
    backgroundSelected: '#2E3135',
    textSecondary: '#B0B4BA',
    accent: '#E2694F',
    accentSoft: '#3A2620',
    accentSecondary: '#E8B75A',
  },
} as const;

/**
 * StageArt Web First Phase 1 (docs/03-BrandIdentity.md): the canonical
 * visual identity - "blackout darkness + warm stage lighting", not the
 * older ポップ・親しみやすい red-orange direction above. Kept as a
 * separate, always-the-same-regardless-of-OS-theme constant (not merged
 * into `Colors.light`/`Colors.dark`) because the brand doc's own default
 * expression is Blackout Black regardless of the user's system light/
 * dark preference - it's a fixed identity for brand-carrying surfaces
 * (startup, the canonical icon/logo, the Web navigation shell), not a
 * dark-mode variant of the existing screens' theme. Existing screens and
 * their `Colors`/`ThemeColor` usage above are untouched by this addition.
 */
export const BrandColors = {
  blackoutBlack: '#0A0A0A',
  stageBeige: '#E8D7B8',
  warmAmber: '#C6892B',
  warmGold: '#E2B15A',
  stageWarmWhite: '#F0D08A',
  softLight: '#FFF6E6',
} as const;

/** Rounded-corner scale (Blueprint §11: "カードや主要ボタンには適度な丸みを持
 * たせる") - a small set of named radii rather than ad-hoc numbers per
 * screen. */
export const Radius = {
  small: 8,
  medium: 14,
  large: 22,
} as const;

export type ThemeColor = keyof typeof Colors.light & keyof typeof Colors.dark;

export const Fonts = Platform.select({
  ios: {
    /** iOS `UIFontDescriptorSystemDesignDefault` */
    sans: 'system-ui',
    /** iOS `UIFontDescriptorSystemDesignSerif` */
    serif: 'ui-serif',
    /** iOS `UIFontDescriptorSystemDesignRounded` */
    rounded: 'ui-rounded',
    /** iOS `UIFontDescriptorSystemDesignMonospaced` */
    mono: 'ui-monospace',
  },
  default: {
    sans: 'normal',
    serif: 'serif',
    rounded: 'normal',
    mono: 'monospace',
  },
  web: {
    sans: 'var(--font-display)',
    serif: 'var(--font-serif)',
    rounded: 'var(--font-rounded)',
    mono: 'var(--font-mono)',
  },
});

export const Spacing = {
  half: 2,
  one: 4,
  two: 8,
  three: 16,
  four: 24,
  five: 32,
  six: 64,
} as const;

export const BottomTabInset = Platform.select({ ios: 50, android: 80 }) ?? 0;
export const MaxContentWidth = 800;
