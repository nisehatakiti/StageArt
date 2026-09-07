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
  // a fixed-size (640px) square that overflows a narrower mobile-width
  // viewport on both sides once centered - without clipping here, that
  // overflow silently widens the page's own scrollable area (visible as
  // a blank strip past the visible edge, and horizontal scroll on Web).
  safeArea: { flex: 1, backgroundColor: STAGE.background, overflow: 'hidden' },
  flex: { flex: 1 },
  scrollContent: { flexGrow: 1, justifyContent: 'center', paddingVertical: Spacing.four },
  contentWrapper: {
    width: '100%',
    maxWidth: AUTH_CONTENT_MAX_WIDTH,
    alignSelf: 'center',
    paddingHorizontal: Spacing.four,
    gap: Spacing.three,
  },
  brand: { alignItems: 'center', marginBottom: Spacing.two },
  // Aspect ratio matches the canonical asset's own viewBox (1400x420 =
  // 10:3) exactly, so this display size never distorts the source image -
  // and the source PNG is 1400x420 natively, so displaying it at roughly
  // 1.7x the previous 240x72 size still has a wide quality margin.
  brandLogo: { width: 400, height: 120 },
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
