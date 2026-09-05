import { useRouter, type Href } from 'expo-router';
import { Modal, StyleSheet, TouchableOpacity, View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { BrandColors, Spacing } from '@/constants/theme';
import { useLogout } from '@/features/mypage/useLogout';
import { confirmAlert } from '@/utils/confirmAlert';

import { useNavMenu, type NavMenuItem } from './useNavMenu';

/**
 * StageArt Blueprint再構成 Phase 1 §27: Native's primary navigation
 * surface - a Hamburger Menu, not Bottom Navigation (Blueprint's
 * explicit instruction, superseding docs/04-CommonNavigationDesign.md's
 * earlier Bottom Nav design per the user's own confirmed decision).
 * `Modal` (not `Alert`) is used deliberately - react-native-web's own
 * `Alert.alert` is a no-op (see confirmAlert.web.tsx's docblock), but
 * `Modal` has a real implementation there too, already proven by that
 * same file's confirm dialog.
 *
 * Logout lives in this drawer AND is reachable via /account (Blueprint
 * §7 - "どの画面からでもアカウント管理とログアウトへ到達できる" - this
 * drawer being reachable from the header on every authenticated screen,
 * via AppChrome, is what actually satisfies that requirement).
 */
export function NativeDrawerMenu({ visible, onClose }: { visible: boolean; onClose: () => void }) {
  const router = useRouter();
  const { basicItems, adminItems } = useNavMenu();
  const logout = useLogout();

  function navigateTo(href: Href) {
    onClose();
    router.push(href);
  }

  function handleLogout() {
    confirmAlert('ログアウト', 'ログアウトしますか？', [
      { text: 'キャンセル', style: 'cancel' },
      {
        text: 'ログアウト',
        style: 'destructive',
        onPress: () => {
          onClose();
          logout();
        },
      },
    ]);
  }

  return (
    <Modal transparent animationType="slide" visible={visible} onRequestClose={onClose} testID="native-drawer-menu">
      <View style={styles.backdrop}>
        <TouchableOpacity
          style={styles.backdropTap}
          onPress={onClose}
          accessibilityRole="button"
          accessibilityLabel="メニューを閉じる"
          testID="native-drawer-backdrop"
        />
        <View style={styles.panel}>
          {basicItems.map((item) => (
            <DrawerLink key={item.key} item={item} onPress={() => navigateTo(item.href)} />
          ))}

          {adminItems.length > 0 && (
            <>
              <View style={styles.divider} />
              {adminItems.map((item) => (
                <DrawerLink key={item.key} item={item} onPress={() => navigateTo(item.href)} />
              ))}
            </>
          )}

          <View style={styles.divider} />
          <TouchableOpacity testID="native-drawer-logout" onPress={handleLogout} style={styles.linkRow}>
            <ThemedText type="default">ログアウト</ThemedText>
          </TouchableOpacity>
        </View>
      </View>
    </Modal>
  );
}

function DrawerLink({ item, onPress }: { item: NavMenuItem; onPress: () => void }) {
  return (
    <TouchableOpacity testID={`native-drawer-${item.key}`} onPress={onPress} style={styles.linkRow}>
      <ThemedText type="default">{item.label}</ThemedText>
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  backdrop: { flex: 1, flexDirection: 'row', backgroundColor: 'rgba(10, 10, 10, 0.5)' },
  backdropTap: { flex: 1 },
  panel: {
    width: 280,
    backgroundColor: '#fff',
    paddingTop: Spacing.six,
    paddingHorizontal: Spacing.four,
  },
  linkRow: { paddingVertical: Spacing.three },
  divider: { height: StyleSheet.hairlineWidth, backgroundColor: '#e1dee6', marginVertical: Spacing.two },
});
