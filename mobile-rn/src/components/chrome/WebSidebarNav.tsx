import { useRouter, type Href } from 'expo-router';
import type { PropsWithChildren } from 'react';
import { StyleSheet, TouchableOpacity, View } from 'react-native';

import { StageArtLogo } from '@/components/brand/StageArtLogo';
import { ThemedText } from '@/components/themed-text';
import { BrandColors, Spacing } from '@/constants/theme';
import { useLogout } from '@/features/mypage/useLogout';
import { confirmAlert } from '@/utils/confirmAlert';

import { useNavMenu, type NavMenuItem } from './useNavMenu';

/**
 * StageArt Phase 1: Web's persistent navigation shell, rebuilt around
 * the Context Area design (docs/04-CommonNavigationDesign.md, confirmed
 * over docs/12-FunctionalStructure.md §15.1's un-migrated flat-menu
 * text - see this Phase's completion report). Layout follows that
 * document's own ordering:
 *
 *   ホーム
 *   ────────
 *   [現在のContext名]
 *   ────────
 *   [Context固有メニュー]
 *   ────────
 *   マイページ / 設定 / ログアウト
 *
 * The header keeps the logo (tap → Home) as the previous shell did;
 * ホーム is additionally its own sidebar entry so the Fixed Area's four
 * items are all present as explicit, labeled links.
 */
export function WebSidebarNav({ children }: PropsWithChildren) {
  const router = useRouter();
  const logout = useLogout();
  const { fixedItems, contextType, contextLabel, contextItems } = useNavMenu();

  const homeItem = fixedItems.find((item) => item.key === 'home')!;
  const bottomFixedItems = fixedItems.filter((item) => item.key !== 'home');

  function handleLogout() {
    confirmAlert('ログアウト', 'ログアウトしますか？', [
      { text: 'キャンセル', style: 'cancel' },
      { text: 'ログアウト', style: 'destructive', onPress: () => logout() },
    ]);
  }

  return (
    <View style={styles.root}>
      <View style={styles.header} testID="web-chrome-header">
        <TouchableOpacity
          testID="web-chrome-logo"
          onPress={() => router.push('/home' as Href)}
          style={styles.headerLogo}
          accessibilityRole="button"
          accessibilityLabel="StageArt ホームへ"
        >
          <StageArtLogo width={120} height={36} />
        </TouchableOpacity>
      </View>

      <View style={styles.body}>
        <View style={styles.sidebar} testID="web-chrome-sidebar">
          <SidebarLink item={homeItem} />

          <View style={styles.divider} />

          {contextType !== 'home' && (
            <ThemedText type="small" themeColor="textSecondary" style={styles.contextLabel} testID="web-chrome-context-label">
              {contextLabel}
            </ThemedText>
          )}

          {contextItems.map((item) => (
            <SidebarLink key={item.key} item={item} />
          ))}

          <View style={styles.divider} />

          {bottomFixedItems.map((item) => (
            <SidebarLink key={item.key} item={item} />
          ))}
          <TouchableOpacity testID="web-chrome-logout" onPress={handleLogout} style={styles.navItem}>
            <ThemedText type="default">ログアウト</ThemedText>
          </TouchableOpacity>
        </View>

        <View style={styles.main}>{children}</View>
      </View>
    </View>
  );
}

function SidebarLink({ item }: { item: NavMenuItem }) {
  const router = useRouter();

  if (item.disabled) {
    return (
      <View testID={`web-chrome-nav-${item.key}`} style={styles.navItem}>
        <ThemedText type="default" themeColor="textSecondary">
          {item.label}
        </ThemedText>
      </View>
    );
  }

  return (
    <TouchableOpacity testID={`web-chrome-nav-${item.key}`} onPress={() => router.push(item.href)} style={styles.navItem}>
      <ThemedText type="default">{item.label}</ThemedText>
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: '#F7F5F1' },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: Spacing.four,
    paddingVertical: Spacing.two,
    backgroundColor: BrandColors.blackoutBlack,
  },
  headerLogo: { paddingVertical: Spacing.one },
  body: { flex: 1, flexDirection: 'row' },
  sidebar: {
    width: 220,
    borderRightWidth: StyleSheet.hairlineWidth,
    borderRightColor: '#e1dee6',
    backgroundColor: '#FFFFFF',
    paddingVertical: Spacing.three,
  },
  navItem: { paddingHorizontal: Spacing.four, paddingVertical: Spacing.two },
  contextLabel: { paddingHorizontal: Spacing.four, paddingBottom: Spacing.one, textTransform: 'uppercase' },
  divider: { height: StyleSheet.hairlineWidth, backgroundColor: '#e1dee6', marginVertical: Spacing.two },
  main: { flex: 1 },
});
