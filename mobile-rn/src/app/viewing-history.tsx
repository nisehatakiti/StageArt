import { StyleSheet } from 'react-native';

import { AppShell } from '@/components/app-shell';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';

/**
 * BusinessFlowUXClarifications.md §06: 観劇履歴 is tied to Person as an
 * AUDIENCE, independent of any stage-activity (Organization/Production
 * membership) affiliation - usable even by a brand-new, unaffiliated
 * user. No audience/viewing-history API exists on the Backend at all
 * (confirmed via this Phase's gap-analysis report), so per the user's
 * decision this route exists now as a real, navigable entry point with
 * a "準備中" state, not an invented API response.
 */
export default function ViewingHistoryScreen() {
  return (
    <AppShell>
      <ThemedView style={styles.container}>
        <ThemedText type="title" style={styles.title}>
          観劇履歴
        </ThemedText>
        <ThemedText themeColor="textSecondary" testID="viewing-history-placeholder" style={styles.body}>
          観劇履歴の記録・閲覧機能は準備中です。公開後は、これまでに観劇した公演・活動をここで振り返れるようになります。
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
