import { usePathname, useRouter } from 'expo-router';
import type { PropsWithChildren } from 'react';
import { Platform, StyleSheet, TouchableOpacity, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { StageArtLogo } from '@/components/brand/StageArtLogo';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { BrandColors, Spacing } from '@/constants/theme';

type BottomNavEntry = { key: string; label: string; route: '/home' | '/discover' | '/favorites' | '/profile' };

const BOTTOM_NAV_ENTRIES: BottomNavEntry[] = [
  { key: 'home', label: 'Home', route: '/home' },
  { key: 'discover', label: '探す', route: '/discover' },
  { key: 'favorites', label: 'お気に入り', route: '/favorites' },
  { key: 'profile', label: 'マイページ', route: '/profile' },
];

/**
 * StageArt Web First Phase 1 (docs/04-CommonNavigationDesign.md §2): the
 * confirmed 4-item Bottom Navigation, persistent on every AppShell-
 * wrapped screen. Web-only - native's navigation pattern is untouched
 * this Phase (native still relies on AppShell's own logo-tap-to-Home,
 * as before). Highlights the currently-active destination via the
 * route's own pathname.
 */
function WebBottomNav() {
  const router = useRouter();
  const pathname = usePathname();

  return (
    <View style={styles.bottomNav} testID="web-bottom-nav">
      {BOTTOM_NAV_ENTRIES.map((entry) => {
        const active = pathname === entry.route;
        return (
          <TouchableOpacity
            key={entry.key}
            testID={`web-bottom-nav-${entry.key}`}
            onPress={() => router.replace(entry.route)}
            style={styles.bottomNavItem}
            accessibilityRole="button"
            accessibilityLabel={entry.label}
          >
            <ThemedText
              type="small"
              style={[styles.bottomNavLabel, active && styles.bottomNavLabelActive]}
            >
              {entry.label}
            </ThemedText>
          </TouchableOpacity>
        );
      })}
    </View>
  );
}

/**
 * Common layout per docs/04-CommonNavigationDesign.md: StageArt logo
 * (top, tap → Home) / main content (middle) / ad space (bottom only -
 * never displacing or centered within main content) / Web-only Bottom
 * Navigation (§2, four fixed destinations). Used by Person Home and
 * other primary screens; auth screens (login/register/etc.)
 * intentionally do not use this shell, since they exist before a Home
 * destination is reachable.
 *
 * The logo is now the canonical StageArt logo lockup
 * (docs/assets/brand/stageart-logo.svg via StageArtLogo), replacing the
 * earlier placeholder text wordmark now that the official asset exists
 * in the Blueprint (docs/03-BrandIdentity.md).
 */
export function AppShell({ children, scroll = false }: PropsWithChildren<{ scroll?: boolean }>) {
  const router = useRouter();

  return (
    <SafeAreaView style={styles.safeArea} edges={['top', 'left', 'right']}>
      <TouchableOpacity
        testID="app-shell-logo"
        onPress={() => router.replace('/home')}
        style={styles.logoRow}
        accessibilityRole="button"
        accessibilityLabel="StageArt ホームへ戻る"
      >
        <StageArtLogo width={132} height={40} />
      </TouchableOpacity>

      <View style={scroll ? styles.scrollableContent : styles.content}>{children}</View>

      <ThemedView type="backgroundElement" style={styles.adSpace} testID="app-shell-ad-space">
        <ThemedText type="small" themeColor="textSecondary">
          広告
        </ThemedText>
      </ThemedView>

      {Platform.OS === 'web' && <WebBottomNav />}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safeArea: { flex: 1 },
  logoRow: {
    paddingHorizontal: Spacing.four,
    paddingVertical: Spacing.two,
    alignItems: 'flex-start',
  },
  content: { flex: 1 },
  scrollableContent: { flex: 1 },
  adSpace: {
    minHeight: 48,
    alignItems: 'center',
    justifyContent: 'center',
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: '#e1dee6',
  },
  bottomNav: {
    flexDirection: 'row',
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: '#e1dee6',
    backgroundColor: BrandColors.blackoutBlack,
  },
  bottomNavItem: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: Spacing.two,
  },
  bottomNavLabel: { color: BrandColors.stageWarmWhite },
  bottomNavLabelActive: { color: BrandColors.warmGold, fontWeight: '700' },
});
