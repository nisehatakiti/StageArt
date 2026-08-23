import { useRouter } from 'expo-router';
import type { PropsWithChildren } from 'react';
import { StyleSheet, TouchableOpacity, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';

/**
 * Common mobile layout per BusinessFlowUXClarifications.md §04/§11:
 * StageArt logo (top, tap → Home) / main content (middle) / ad space
 * (bottom only - never displacing or centered within main content, on
 * iPad or otherwise). Used by Person Home and other primary screens;
 * auth screens (login/register/etc.) intentionally do not use this
 * shell, since they exist before a Home destination is reachable.
 *
 * The official StageArt logo asset ("S + 脚立A" per the Blueprint) does
 * not exist in this repo (confirmed: assets/images/icon.png is only the
 * generic Expo template icon) - a styled text wordmark stands in as a
 * clearly-flagged placeholder until the real asset is provided.
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
        <ThemedText type="subtitle" themeColor="accent" style={styles.logoText}>
          StageArt
        </ThemedText>
      </TouchableOpacity>

      <View style={scroll ? styles.scrollableContent : styles.content}>{children}</View>

      <ThemedView type="backgroundElement" style={styles.adSpace} testID="app-shell-ad-space">
        <ThemedText type="small" themeColor="textSecondary">
          広告
        </ThemedText>
      </ThemedView>
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
  logoText: { fontSize: 22, lineHeight: 28, fontWeight: '800' },
  content: { flex: 1 },
  scrollableContent: { flex: 1 },
  adSpace: {
    minHeight: 48,
    alignItems: 'center',
    justifyContent: 'center',
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: '#e1dee6',
  },
});
