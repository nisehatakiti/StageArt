import { StyleSheet } from 'react-native';

import { Spacing } from '@/constants/theme';

import { AUTH_CONTENT_MAX_WIDTH, STAGE } from './authTheme';

/**
 * 認証画面デザイン統一: the one shared style set every auth screen
 * (login/register/forgot-password/reset-password/verify-email/
 * registration-pending) draws its title/description/input/button/link/
 * error styling from, so a screen can't independently drift back to a
 * white background or a purple button the way register.tsx etc.
 * previously did. Kept intentionally small - only what genuinely repeats
 * identically across every screen; each screen's own StyleSheet still
 * owns whatever is unique to it (Google button, dividers, secondary
 * buttons, etc).
 */
export const authStyles = StyleSheet.create({
  // overflow: 'hidden' matters on web specifically: AuthSpotlight renders
  // a fixed-size (820px) square that overflows a narrower mobile-width
  // viewport on both sides once centered - without clipping here, that
  // overflow silently widens the page's own scrollable area (visible as
  // a blank strip past the visible edge, and horizontal scroll on Web).
  safeArea: { flex: 1, backgroundColor: STAGE.background, overflow: 'hidden' },
  flex: { flex: 1 },
  // StageArt 認証画面 ロゴ強化 (2026-09-07): flex-start + paddingTop (not
  // justifyContent:'center') so the brand block sits noticeably higher
  // than screen-center on a tall PC viewport, per the "ロゴブロックが
  // ログインエリア全体の少し上寄りに配置される" requirement - centering
  // would otherwise push a now-taller brand block back down toward the
  // middle, undoing the repositioning.
  scrollContent: { flexGrow: 1, justifyContent: 'flex-start', paddingTop: Spacing.six, paddingBottom: Spacing.four },
  contentWrapper: {
    width: '100%',
    maxWidth: AUTH_CONTENT_MAX_WIDTH,
    alignSelf: 'center',
    paddingHorizontal: Spacing.four,
    gap: Spacing.three,
  },
  brand: { alignItems: 'center', marginBottom: Spacing.one },
  // stageart-logo-icon-wordmark.png is a tight crop of the canonical
  // docs/assets/brand/stageart-logo.svg (icon + "StageArt" wordmark only
  // - the Japanese tagline <text> element is deliberately excluded, not
  // merely scaled down, since it renders unreadably small baked into a
  // raster image at any practical display width; see `tagline` below,
  // which renders it as real HTML/RN text instead so its size is
  // independently controllable). Source is 3150x930 (rasterized at 3x
  // from a 1050x310 viewBox), so this display width is still well inside
  // its quality margin. width: '100%' (not a fixed pixel width) so the
  // logo fills contentWrapper's own available width on every screen size
  // - a fixed width wide enough to look prominent on PC (contentWrapper
  // capped at AUTH_CONTENT_MAX_WIDTH=460) overflowed a narrow mobile
  // viewport, where contentWrapper is only ~390 - 2*Spacing.four wide.
  // `aspectRatio` (CSS) was tried first but did not resolve correctly in
  // this RN-Web static export - the Image kept its raw intrinsic pixel
  // height (930) regardless, ballooning the layout. A fixed `height` +
  // resizeMode="contain" avoids that: contain never overflows its own
  // box on the width axis, and on a narrower box the rendered glyphs
  // simply end up smaller (letterboxed within this height), never
  // clipped or stretched.
  brandLogo: { width: '100%', height: 130 },
  tagline: {
    textAlign: 'center',
    color: STAGE.tagline,
    fontSize: 15,
    lineHeight: 22,
    letterSpacing: 0.3,
  },
  title: { fontSize: 26, lineHeight: 32, textAlign: 'center', color: STAGE.inputText },
  description: { textAlign: 'center', color: STAGE.placeholder },
  input: {
    borderWidth: 1,
    borderColor: STAGE.inputBorder,
    borderRadius: 8,
    paddingHorizontal: Spacing.three,
    paddingVertical: 12,
    fontSize: 16,
    backgroundColor: STAGE.inputBackground,
    color: STAGE.inputText,
  },
  error: { color: STAGE.error },
  button: {
    backgroundColor: STAGE.accent,
    borderRadius: 8,
    paddingVertical: 13,
    alignItems: 'center',
    marginTop: Spacing.two,
  },
  buttonDisabled: { opacity: 0.6 },
  buttonText: { color: '#fff', fontWeight: '600' },
  buttonSecondary: {
    borderWidth: 1,
    borderColor: STAGE.accent,
    borderRadius: 8,
    paddingVertical: 13,
    alignItems: 'center',
    marginTop: Spacing.two,
  },
  buttonSecondaryText: { color: STAGE.accent, fontWeight: '700' },
  linkCentered: { textAlign: 'center', color: STAGE.link },
});
