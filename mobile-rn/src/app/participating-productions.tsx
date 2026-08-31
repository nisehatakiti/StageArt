import { useRouter } from 'expo-router';
import { ActivityIndicator, StyleSheet, TouchableOpacity } from 'react-native';

import { AppShell } from '@/components/app-shell';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';
import { useMyDashboard } from '@/features/dashboard/useDashboard';
import { getErrorMessage } from '@/utils/errorMessage';

/**
 * BusinessFlowUXClarifications.md §02.4: shows Productions the Person is
 * currently participating in, or a natural empty state (never an error)
 * right after registration when there are none. No dedicated
 * "participation list" API exists on the Backend, so this reuses the
 * existing GET /me/dashboard's upcoming_rehearsals (Attendance-based,
 * cross-Production, already real data - see this Phase's gap-analysis
 * report) grouped by Production - a best-available proxy, not a
 * placeholder: unlike discover-organizations/discover-productions/
 * viewing-history, this is real data the Backend already returns today.
 * A Production with no upcoming Rehearsal will not appear here - a
 * disclosed limitation of this proxy, not a bug.
 */
export default function ParticipatingProductionsScreen() {
  const router = useRouter();
  const dashboardQuery = useMyDashboard();

  const productions = dashboardQuery.data
    ? dedupeProductions(dashboardQuery.data.upcoming_rehearsals)
    : [];

  return (
    <AppShell>
      <ThemedView style={styles.container}>
        <ThemedText type="title" style={styles.title}>
          参加している公演・活動
        </ThemedText>

        {dashboardQuery.isLoading && <ActivityIndicator testID="participating-productions-loading" />}

        {dashboardQuery.isError && (
          <ThemedText testID="participating-productions-error" style={styles.body}>
            {getErrorMessage(dashboardQuery.error)}
          </ThemedText>
        )}

        {!dashboardQuery.isLoading && !dashboardQuery.isError && productions.length === 0 && (
          <ThemedText themeColor="textSecondary" testID="participating-productions-empty" style={styles.body}>
            参加している公演・活動はありません。
          </ThemedText>
        )}

        {productions.length > 0 && (
          <ThemedView testID="participating-productions-list" style={styles.list}>
            {productions.map((production) => (
              <TouchableOpacity
                key={production.productionId}
                testID={`participating-production-row-${production.productionId}`}
                style={styles.card}
                onPress={() => router.push(`/production/${production.productionId}/schedule`)}
              >
                <ThemedText type="smallBold">{production.productionName}</ThemedText>
              </TouchableOpacity>
            ))}
          </ThemedView>
        )}
      </ThemedView>
    </AppShell>
  );
}

export function dedupeProductions(
  upcomingRehearsals: { production_id: string; production_name: string }[]
): { productionId: string; productionName: string }[] {
  const seen = new Map<string, string>();
  for (const rehearsal of upcomingRehearsals) {
    if (!seen.has(rehearsal.production_id)) {
      seen.set(rehearsal.production_id, rehearsal.production_name);
    }
  }
  return Array.from(seen, ([productionId, productionName]) => ({ productionId, productionName }));
}

const styles = StyleSheet.create({
  container: { flex: 1, padding: Spacing.four, gap: Spacing.three },
  title: { fontSize: 22, lineHeight: 28 },
  body: { textAlign: 'center', paddingTop: Spacing.four },
  list: { gap: Spacing.two },
  card: {
    borderWidth: 1,
    borderColor: '#e1dee6',
    borderRadius: 10,
    padding: Spacing.three,
  },
});
