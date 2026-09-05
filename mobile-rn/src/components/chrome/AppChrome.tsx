import { useRouter, type Href } from 'expo-router';
import { useState, type PropsWithChildren } from 'react';
import { Platform, StyleSheet, TouchableOpacity, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { StageArtLogo } from '@/components/brand/StageArtLogo';
import { ThemedText } from '@/components/themed-text';
import { BrandColors, Spacing } from '@/constants/theme';

import { NativeDrawerMenu } from './NativeDrawerMenu';
import { WebSidebarNav } from './WebSidebarNav';

/**
 * StageArt Blueprint再構成 Phase 1: the one authenticated-shell entry
 * point every screen under app/(app)/ renders inside (see
 * app/(app)/_layout.tsx) - replaces the previous per-screen
 * AppShell/WebLayout opt-in, so "reachable from every authenticated
 * screen" (Blueprint §7) holds by construction rather than by each
 * screen remembering to wrap itself.
 *
 * Web keeps a persistent sidebar (WebSidebarNav); Native gets a
 * Hamburger Menu (NativeDrawerMenu) instead of Bottom Navigation
 * (Blueprint §27, superseding docs/04-CommonNavigationDesign.md's
 * earlier Bottom Nav design - user-confirmed).
 */
export function AppChrome({ children }: PropsWithChildren) {
  if (Platform.OS === 'web') {
    return <WebSidebarNav>{children}</WebSidebarNav>;
  }

  return <NativeChrome>{children}</NativeChrome>;
}

function NativeChrome({ children }: PropsWithChildren) {
  const router = useRouter();
  const [menuOpen, setMenuOpen] = useState(false);

  return (
    <View style={styles.root}>
      <SafeAreaView edges={['top']} style={styles.header}>
        <TouchableOpacity
          testID="app-chrome-logo"
          onPress={() => router.push('/home' as Href)}
          accessibilityRole="button"
          accessibilityLabel="StageArt ホームへ"
        >
          <StageArtLogo width={110} height={34} />
        </TouchableOpacity>
        <TouchableOpacity
          testID="app-chrome-menu-button"
          onPress={() => setMenuOpen(true)}
          accessibilityRole="button"
          accessibilityLabel="メニューを開く"
          style={styles.menuButton}
        >
          <ThemedText type="default" style={styles.menuButtonText}>
            ☰
          </ThemedText>
        </TouchableOpacity>
      </SafeAreaView>

      <View style={styles.content}>{children}</View>

      <NativeDrawerMenu visible={menuOpen} onClose={() => setMenuOpen(false)} />
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1 },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: Spacing.four,
    paddingVertical: Spacing.two,
    backgroundColor: BrandColors.blackoutBlack,
  },
  menuButton: { padding: Spacing.one },
  menuButtonText: { color: BrandColors.stageWarmWhite, fontSize: 22 },
  content: { flex: 1 },
});
