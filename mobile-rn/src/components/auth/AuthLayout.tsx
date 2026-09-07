import type { ReactNode } from 'react';
import { Image, KeyboardAvoidingView, Platform, ScrollView, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { authStyles } from './authStyles';
import { AuthSpotlight } from './AuthSpotlight';
import { ThemedText } from '@/components/themed-text';

/**
 * StageArt 認証画面デザイン統一 (2026-09-07): the one shared shell for
 * every authentication screen - dark background, soft central spotlight,
 * StageArt logo, then whatever form content each screen supplies as
 * children. Concept: "StageArtの舞台に立つのは、今操作しているあなた自身" -
 * before this, only login.tsx rendered this way; register/forgot-
 * password/reset-password/verify-email/registration-pending each
 * rendered their own default light-theme ThemedView, so navigating
 * between them looked like leaving the app entirely.
 *
 * A ScrollView (not the plain centered View login.tsx used to have)
 * keeps this robust once the logo above got noticeably bigger and the
 * spotlight was added - content that doesn't fit a short phone screen
 * (or is pushed up by the keyboard) scrolls instead of being clipped,
 * while the background/spotlight stay fixed behind it (rendered outside
 * the ScrollView, not scrolled with the content).
 *
 * StageArt 認証画面 ロゴ強化 (2026-09-07): the Japanese tagline is real
 * HTML/RN text (authStyles.tagline), not baked into the logo image - the
 * canonical logo SVG draws it at font-size 25 inside a 420-tall viewBox,
 * which becomes unreadably small at any raster display size a real login
 * screen would use. stageart-logo-icon-wordmark.png is a tight crop of
 * that same canonical SVG with the tagline <text> element removed
 * entirely, so the icon+wordmark can be sized independently of (and
 * larger than) the tagline, whose own font size is now controlled here.
 */
export function AuthLayout({ children, logoTestID = 'auth-brand-logo' }: { children: ReactNode; logoTestID?: string }) {
  return (
    <SafeAreaView style={authStyles.safeArea}>
      <AuthSpotlight />
      <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={authStyles.flex}>
        <ScrollView style={authStyles.flex} contentContainerStyle={authStyles.scrollContent} keyboardShouldPersistTaps="handled">
          <View style={authStyles.contentWrapper}>
            <View style={authStyles.brand}>
              <Image
                testID={logoTestID}
                accessibilityLabel="StageArt"
                source={require('../../../assets/images/stageart-logo-icon-wordmark.png')}
                style={authStyles.brandLogo}
                resizeMode="contain"
              />
              <ThemedText style={authStyles.tagline}>舞台と人をつなぐ、すべての人のために。</ThemedText>
            </View>
            {children}
          </View>
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}
