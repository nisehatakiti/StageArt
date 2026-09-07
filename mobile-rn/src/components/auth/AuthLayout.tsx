import type { ReactNode } from 'react';
import { Image, KeyboardAvoidingView, Platform, ScrollView, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { authStyles } from './authStyles';
import { AuthSpotlight } from './AuthSpotlight';

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
                source={require('../../../assets/images/stageart-logo-lockup.png')}
                style={authStyles.brandLogo}
                resizeMode="contain"
              />
            </View>
            {children}
          </View>
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}
