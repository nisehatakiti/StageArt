import { StyleSheet } from 'react-native';

import { AppShell } from '@/components/app-shell';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';

/**
 * BusinessFlowUXClarifications.md §05: an Organization-centric discovery
 * entry point, distinct from discover-productions.tsx - neither
 * audience-only nor performer-only. The Backend currently has no public/
 * general Organization search API (GET /organizations is Membership-
 * scoped only - confirmed via this Phase's gap-analysis report), so per
 * the user's own decision (discovery-API-gap Option A: "導線のみ先行実装"),
 * this route exists now as a real, navigable entry point with a
 * "準備中" state - real search/listing is deferred to a future
 * Backend-dependent phase, not invented here.
 */
export default function DiscoverOrganizationsScreen() {
  return (
    <AppShell>
      <ThemedView style={styles.container}>
        <ThemedText type="title" style={styles.title}>
          団体を探す
        </ThemedText>
        <ThemedText themeColor="textSecondary" testID="discover-organizations-placeholder" style={styles.body}>
          団体の検索機能は準備中です。公開後は、団体の情報・現在の活動・今後の公演/活動・過去の公演/活動をここから確認できるようになります。
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
