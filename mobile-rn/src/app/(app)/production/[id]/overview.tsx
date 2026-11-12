import { useLocalSearchParams, useRouter } from 'expo-router';
import { useMemo } from 'react';
import { ActivityIndicator, ScrollView, StyleSheet, TouchableOpacity } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';
import { buildUpcomingRehearsalViewModel } from '@/features/dashboard/viewModel';
import { useMyDashboard } from '@/features/dashboard/useDashboard';
import { useMyParticipatingProductions } from '@/features/participant/useParticipant';
import { useProductionOverview } from '@/features/production/useProductions';
import { getErrorMessage } from '@/utils/errorMessage';

const PARTICIPANT_TYPE_LABEL: Record<string, string> = { CAST: '出演者', STAFF: 'スタッフ' };

/**
 * 参加者向け「公演概要ダッシュボード」instruction: 「参加している公演・活動」
 * からProductionをタップした際の到達点。docs/04-CommonNavigationDesign.md
 * §7（「概要」が参加者の入口）および docs/04-HomeRoleBasedMenu.md §05
 * （Production情報・次回稽古・出欠確認）に基づき、稽古作成・タイムテーブル
 * 作成/編集/印刷・受付管理など、Production管理Context側の操作は一切置か
 * ない（schedule/index.tsx はこれらの管理操作とWeb管理サイドメニューの
 * 「稽古管理」導線を兼ねているため、このダッシュボードとは別の画面として
 * 新設した - 既存のschedule/index.tsx自体は変更していない）。
 *
 * データソース:
 * - 公演基本情報: GET /productions/{id}/overview
 *   (useProductionOverview - isProductionMember-gated、新規追加。
 *   既存の GET /productions/{id} は canReadProduction
 *   (PrimaryManager/ActiveDelegateのみ) で一般Participantを403にするため
 *   再利用できなかった - 詳細は GetProductionOverviewUseCase.php 参照)
 * - 次回の稽古・出欠確認: GET /me/dashboard の upcoming_rehearsals を
 *   このProduction IDで絞り込み（既存API再利用、新規APIなし）
 * - 参加者本人に関係する情報: GET /me/participating-productions を
 *   このProduction IDで絞り込み（既存API再利用、新規APIなし）
 */
export default function ProductionOverviewScreen() {
  const { id: productionId } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();

  const overviewQuery = useProductionOverview(productionId);
  const dashboardQuery = useMyDashboard();
  const myParticipationsQuery = useMyParticipatingProductions();

  const upcomingRehearsals = useMemo(() => {
    if (!dashboardQuery.data) {
      return [];
    }
    return dashboardQuery.data.upcoming_rehearsals
      .filter((rehearsal) => rehearsal.production_id === productionId)
      .map(buildUpcomingRehearsalViewModel);
  }, [dashboardQuery.data, productionId]);

  const myParticipation = useMemo(
    () => myParticipationsQuery.data?.find((participation) => participation.production_id === productionId) ?? null,
    [myParticipationsQuery.data, productionId]
  );

  const isLoading = overviewQuery.isLoading;
  const isError = overviewQuery.isError;
  const production = overviewQuery.data;

  return (
    <SafeAreaView style={styles.safeArea}>
      {isLoading && (
        <ThemedView style={styles.centered}>
          <ActivityIndicator testID="production-overview-loading" />
        </ThemedView>
      )}

      {isError && (
        <ThemedView style={styles.centered}>
          <ThemedText testID="production-overview-error">{getErrorMessage(overviewQuery.error)}</ThemedText>
        </ThemedView>
      )}

      {!isLoading && !isError && production && (
        <ScrollView testID="production-overview-content" contentContainerStyle={styles.content}>
          <ThemedView style={styles.section}>
            {production.title_heading && (
              <ThemedText type="small" themeColor="textSecondary" testID="production-overview-title-heading">
                {production.title_heading}
              </ThemedText>
            )}
            <ThemedText type="title" testID="production-overview-name">
              {production.name}
            </ThemedText>
          </ThemedView>

          {myParticipation && (
            <ThemedView style={styles.section} testID="production-overview-my-participation">
              <ThemedText type="small" themeColor="textSecondary">
                あなたの参加区分
              </ThemedText>
              <ThemedText type="smallBold" testID="production-overview-my-participant-type">
                {PARTICIPANT_TYPE_LABEL[myParticipation.participant_type] ?? myParticipation.participant_type}
              </ThemedText>
            </ThemedView>
          )}

          {(production.venue_name || production.schedule_start_date || production.description) && (
            <ThemedView style={styles.section} testID="production-overview-basic-info">
              <ThemedText type="subtitle">公演基本情報</ThemedText>
              {production.venue_name && <ThemedText testID="production-overview-venue">{production.venue_name}</ThemedText>}
              {production.schedule_start_date && (
                <ThemedText testID="production-overview-schedule">
                  {production.schedule_start_date}
                  {production.schedule_end_date && production.schedule_end_date !== production.schedule_start_date
                    ? ` 〜 ${production.schedule_end_date}`
                    : ''}
                </ThemedText>
              )}
              {production.description && <ThemedText testID="production-overview-description">{production.description}</ThemedText>}
            </ThemedView>
          )}

          <ThemedView style={styles.section}>
            <ThemedText type="subtitle">次回の稽古</ThemedText>
            {upcomingRehearsals.length === 0 && (
              <ThemedText themeColor="textSecondary" testID="production-overview-no-upcoming-rehearsal">
                次回の稽古予定はありません。
              </ThemedText>
            )}
            {upcomingRehearsals.length > 0 && (
              <ThemedView testID="production-overview-upcoming-rehearsals" style={styles.list}>
                {upcomingRehearsals.map((rehearsal) => (
                  <TouchableOpacity
                    key={rehearsal.rehearsalId}
                    testID={`production-overview-rehearsal-row-${rehearsal.rehearsalId}`}
                    style={styles.card}
                    onPress={() => router.push(`/production/${productionId}/schedule/attendance/${rehearsal.rehearsalId}`)}
                  >
                    <ThemedView style={styles.titleRow}>
                      {rehearsal.isUnanswered && (
                        <ThemedView style={styles.unansweredDot} testID={`production-overview-unanswered-dot-${rehearsal.rehearsalId}`} />
                      )}
                      <ThemedText type="smallBold">{rehearsal.title ?? '稽古'}</ThemedText>
                    </ThemedView>
                    {rehearsal.dateDisplay && (
                      <ThemedText type="small" themeColor="textSecondary">
                        {rehearsal.dateDisplay}
                        {rehearsal.timeDisplay ? ` ${rehearsal.timeDisplay}` : ''}
                        {rehearsal.location ? ` ・ ${rehearsal.location}` : ''}
                      </ThemedText>
                    )}
                  </TouchableOpacity>
                ))}
              </ThemedView>
            )}
          </ThemedView>
        </ScrollView>
      )}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safeArea: { flex: 1 },
  centered: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: Spacing.four },
  content: { padding: Spacing.four, gap: Spacing.four },
  section: { gap: Spacing.two },
  list: { gap: Spacing.two },
  card: {
    borderWidth: 1,
    borderColor: '#e1dee6',
    borderRadius: 10,
    padding: Spacing.three,
    gap: 2,
  },
  titleRow: { flexDirection: 'row', alignItems: 'center', gap: Spacing.two },
  unansweredDot: { width: 8, height: 8, borderRadius: 4, backgroundColor: '#C6892B' },
});
