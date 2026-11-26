import { useRouter, type Href } from 'expo-router';
import { Modal, StyleSheet, TouchableOpacity, View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { Spacing } from '@/constants/theme';
import { useLogout } from '@/features/mypage/useLogout';
import { confirmAlert } from '@/utils/confirmAlert';

import { useNavMenu, type NavMenuItem } from './useNavMenu';

/**
 * StageArt Phase 1: Native's Hamburger Menu, rebuilt around the Context
 * Area design (same source and layout ordering as WebSidebarNav.tsx -
 * see that file's docblock). `Modal` (not `Alert`) is used deliberately
 * - react-native-web's own `Alert.alert` is a no-op (see
 * confirmAlert.web.tsx's docblock), but `Modal` has a real
 * implementation there too.
 */
export function NativeDrawerMenu({ visible, onClose }: { visible: boolean; onClose: () => void }) {
  const router = useRouter();
  const { fixedItems, contextType, contextLabel, contextItems, backTo } = useNavMenu();
  const logout = useLogout();

  const homeItem = fixedItems.find((item) => item.key === 'home')!;
  const bottomFixedItems = fixedItems.filter((item) => item.key !== 'home');

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
          <DrawerLink item={homeItem} onPress={() => navigateTo(homeItem.href)} />

          <View style={styles.divider} />

          {contextType !== 'home' && (
            <ThemedText type="small" themeColor="textSecondary" style={styles.contextLabel} testID="native-drawer-context-label">
              {contextLabel}
            </ThemedText>
          )}

          {backTo && (
            <TouchableOpacity testID="native-drawer-back-to" onPress={() => navigateTo(backTo.href)} style={styles.linkRow}>
              <ThemedText type="small" themeColor="textSecondary">
                {backTo.label}
              </ThemedText>
            </TouchableOpacity>
          )}

          {contextItems.map((item) => (
            <View key={item.key}>
              {item.groupLabel && (
                <ThemedText type="small" themeColor="textSecondary" style={styles.groupLabel} testID={`native-drawer-group-${item.key}`}>
                  {item.groupLabel}
                </ThemedText>
              )}
              <DrawerLink item={item} onPress={() => navigateTo(item.href)} />
            </View>
          ))}

          <View style={styles.divider} />

          {bottomFixedItems.map((item) => (
            <DrawerLink key={item.key} item={item} onPress={() => navigateTo(item.href)} />
          ))}

          <TouchableOpacity testID="native-drawer-logout" onPress={handleLogout} style={styles.linkRow}>
            <ThemedText type="default">ログアウト</ThemedText>
          </TouchableOpacity>
        </View>
      </View>
    </Modal>
  );
}

function DrawerLink({ item, onPress }: { item: NavMenuItem; onPress: () => void }) {
  if (item.disabled) {
    return (
      <View testID={`native-drawer-${item.key}`} style={styles.linkRow}>
        <ThemedText type="default" themeColor="textSecondary">
          {item.label}
        </ThemedText>
      </View>
    );
  }

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
  contextLabel: { paddingVertical: Spacing.one, textTransform: 'uppercase' },
  groupLabel: { paddingTop: Spacing.one },
  divider: { height: StyleSheet.hairlineWidth, backgroundColor: '#e1dee6', marginVertical: Spacing.two },
});
