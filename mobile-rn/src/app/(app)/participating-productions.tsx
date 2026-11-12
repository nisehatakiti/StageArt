import { useRouter } from 'expo-router';
import { ActivityIndicator, StyleSheet, TouchableOpacity } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';
import { useMyParticipatingProductions } from '@/features/participant/useParticipant';
import { getErrorMessage } from '@/utils/errorMessage';

/**
 * BusinessFlowUXClarifications.md §02 ("参加している公演・活動") / §07
 * ("本人確認・承認を経て参加を確定する"): shows every Production the caller
 * is a confirmed (ACTIVE) PERSON Participant of, or a natural empty
 * state (never an error) right after registration when there are none.
 *
 * Sourced from GET /me/participating-productions
 * (ListMyParticipatingProductionsUseCase.php), which reads
 * ProductionParticipant directly - NOT GET /me/dashboard's
 * upcoming_rehearsals, which this screen used to reuse as a proxy. That
 * proxy was Attendance-based, so a Production with no upcoming
 * Rehearsal (or a Participant with no Attendance rows at all) never
 * appeared here even when the Person was genuinely an ACTIVE
 * Participant - this screen now reflects Participant status, not
 * Rehearsal scheduling.
 *
 * 参加者向け「公演概要ダッシュボード」instruction: tapping a Production
 * navigates to `/production/{id}/overview` (参加者向け公演概要), not
 * `/production/{id}/schedule` - that screen also serves as the Web
 * Production管理Context sidebar's「稽古管理」destination
 * (useNavMenu.ts) and shows Production管理操作 (タイムテーブル作成/印刷,
 * 出欠入口) that are not appropriate for a Participant arriving from
 * this list. See overview.tsx's own docblock.
 */
export default function ParticipatingProductionsScreen() {
  const router = useRouter();
  const participatingProductionsQuery = useMyParticipatingProductions();

  const productions = participatingProductionsQuery.data ?? [];

  return (
    <>
      <ThemedView style={styles.container}>
        <ThemedText type="title" style={styles.title}>
          参加している公演・活動
        </ThemedText>

        {participatingProductionsQuery.isLoading && <ActivityIndicator testID="participating-productions-loading" />}

        {participatingProductionsQuery.isError && (
          <ThemedText testID="participating-productions-error" style={styles.body}>
            {getErrorMessage(participatingProductionsQuery.error)}
          </ThemedText>
        )}

        {!participatingProductionsQuery.isLoading && !participatingProductionsQuery.isError && productions.length === 0 && (
          <ThemedText themeColor="textSecondary" testID="participating-productions-empty" style={styles.body}>
            参加している公演・活動はありません。
          </ThemedText>
        )}

        {productions.length > 0 && (
          <ThemedView testID="participating-productions-list" style={styles.list}>
            {productions.map((production) => (
              <TouchableOpacity
                key={production.production_id}
                testID={`participating-production-row-${production.production_id}`}
                style={styles.card}
                onPress={() => router.push(`/production/${production.production_id}/overview`)}
              >
                <ThemedText type="smallBold">{production.production_name}</ThemedText>
              </TouchableOpacity>
            ))}
          </ThemedView>
        )}
      </ThemedView>
    </>
  );
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
