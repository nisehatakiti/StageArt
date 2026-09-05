import { useRouter, type Href } from 'expo-router';
import type { PropsWithChildren } from 'react';
import { StyleSheet, TouchableOpacity, View } from 'react-native';

import { StageArtLogo } from '@/components/brand/StageArtLogo';
import { ThemedText } from '@/components/themed-text';
import { BrandColors, Spacing } from '@/constants/theme';
import { useCurrentPerson } from '@/features/person/useCurrentPerson';
import { useLogout } from '@/features/mypage/useLogout';
import { confirmAlert } from '@/utils/confirmAlert';

import { useNavMenu, type NavMenuItem } from './useNavMenu';

/**
 * StageArt Blueprint再構成 Phase 1: Web's persistent navigation shell -
 * header (logo + user name + アカウント管理 + ログアウト, always visible on
 * every authenticated screen, never buried behind Home/Profile) + a
 * fixed-width left sidebar listing useNavMenu()'s basic/admin items.
 * Replaces WebLayout.tsx's sidebar - deliberately does NOT carry over
 * WebLayout's Organization/Production submenu (it showed every
 * management link, including 会計, with no permission check at all -
 * see the Phase 1 plan's own note on this bug). Per-Organization/
 * per-Production management surfaces already live on their own detail
 * pages' own menu grids (organizations/[id]/index.tsx,
 * productions/[id]/index.tsx), which already gate correctly.
 */
export function WebSidebarNav({ children }: PropsWithChildren) {
  const router = useRouter();
  const currentPersonQuery = useCurrentPerson();
  const logout = useLogout();
  const { basicItems, adminItems } = useNavMenu();

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
        <View style={styles.headerRight}>
          <ThemedText type="small" style={styles.headerUser} testID="web-chrome-user">
            {currentPersonQuery.data ? [currentPersonQuery.data.family_name, currentPersonQuery.data.given_name].filter(Boolean).join(' ') : ''}
          </ThemedText>
          <TouchableOpacity testID="web-chrome-account" onPress={() => router.push('/account' as Href)}>
            <ThemedText type="link">アカウント管理</ThemedText>
          </TouchableOpacity>
          <TouchableOpacity testID="web-chrome-logout" onPress={handleLogout}>
            <ThemedText type="link">ログアウト</ThemedText>
          </TouchableOpacity>
        </View>
      </View>

      <View style={styles.body}>
        <View style={styles.sidebar} testID="web-chrome-sidebar">
          {basicItems.map((item) => (
            <SidebarLink key={item.key} item={item} />
          ))}

          {adminItems.length > 0 && (
            <>
              <View style={styles.divider} />
              {adminItems.map((item) => (
                <SidebarLink key={item.key} item={item} />
              ))}
            </>
          )}
        </View>

        <View style={styles.main}>{children}</View>
      </View>
    </View>
  );
}

function SidebarLink({ item }: { item: NavMenuItem }) {
  const router = useRouter();
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
    justifyContent: 'space-between',
    paddingHorizontal: Spacing.four,
    paddingVertical: Spacing.two,
    backgroundColor: BrandColors.blackoutBlack,
  },
  headerLogo: { paddingVertical: Spacing.one },
  headerRight: { flexDirection: 'row', alignItems: 'center', gap: Spacing.three },
  headerUser: { color: BrandColors.stageWarmWhite },
  body: { flex: 1, flexDirection: 'row' },
  sidebar: {
    width: 220,
    borderRightWidth: StyleSheet.hairlineWidth,
    borderRightColor: '#e1dee6',
    backgroundColor: '#FFFFFF',
    paddingVertical: Spacing.three,
  },
  navItem: { paddingHorizontal: Spacing.four, paddingVertical: Spacing.two },
  divider: { height: StyleSheet.hairlineWidth, backgroundColor: '#e1dee6', marginVertical: Spacing.two },
  main: { flex: 1 },
});
