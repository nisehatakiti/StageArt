import { StyleSheet } from 'react-native';

import { AppShell } from '@/components/app-shell';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';

/**
 * BusinessFlowUXClarifications.md §05: a Production-centric discovery
 * entry point (when/where/what-kind/which-organization's Productions
 * can be found, including past ones) - not a renamed copy of
 * discover-organizations.tsx. No public/general Production search API
 * exists on the Backend today (GET /productions is PrimaryManager/
 * Delegate-scoped only), so per the user's decision this route exists
 * now as a real, navigable entry point with a "準備中" state.
 */
export default function DiscoverProductionsScreen() {
  return (
    <AppShell>
      <ThemedView style={styles.container}>
        <ThemedText type="title" style={styles.title}>
          公演・活動を探す
        </ThemedText>
        <ThemedText themeColor="textSecondary" testID="discover-productions-placeholder" style={styles.body}>
          公演・活動の検索機能は準備中です。公開後は、開催時期・場所・種類・主催団体から公演・活動を探せるようになります（過去の公演・活動も含みます）。
        </ThemedText>
      </ThemedView>
    </AppShell>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: Spacing.four, gap: Spacing.two },
  title: { fontSize: 22, lineHeight: 28 },
  body: { textAlign: 'center' },
});
