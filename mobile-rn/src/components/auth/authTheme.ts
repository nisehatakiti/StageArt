import { BrandColors } from '@/constants/theme';

/**
 * StageArt 認証画面デザイン統一 (2026-09-07): the single dark "theatre"
 * palette shared by every authentication screen (login/register/forgot-
 * password/reset-password/verify-email/registration-pending) - moved out
 * of login.tsx (which used to define this locally) so no screen drifts
 * back toward the app's default light/dark-mode-aware ThemedView/
 * ThemedText colors, which is exactly how register.tsx etc. ended up
 * white-background-with-a-purple-button while login.tsx alone went dark.
 * docs/03-BrandIdentity.md's "blackout black + warm stage illumination"
 * is a fixed identity for this whole screen family, not a dark-mode
 * variant - unrelated to the user's OS light/dark preference.
 */
export const STAGE = {
  background: '#050505',
  spotlight: BrandColors.warmGold,
  inputBackground: '#101010',
  inputBorder: '#2A2620',
  inputText: '#F5EFE3',
  placeholder: '#8A8272',
  divider: '#3A342C',
  link: BrandColors.stageWarmWhite,
  error: '#E2836B',
  // StageArt Blueprint's specified bronze - matches the logo mark's own
  // A-color exactly. The one accent color for every primary action across
  // every auth screen (never the purple register.tsx/forgot-password.tsx/
  // reset-password.tsx/verify-email.tsx/registration-pending.tsx used to
  // have independently).
  accent: '#C89B5E',
} as const;

/** Auth forms never stretch to the full width of a wide PC/web viewport -
 * capped and centered, per the unified auth layout's explicit sizing
 * instruction (400-520px target range). */
export const AUTH_CONTENT_MAX_WIDTH = 460;
