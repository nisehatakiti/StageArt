import { useLocalSearchParams } from 'expo-router';
import { ActivityIndicator, ScrollView, StyleSheet, View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { Spacing } from '@/constants/theme';
import { useMemberPerformanceSummary } from '@/features/memberPerformanceSummary/useMemberPerformanceSummary';
import { useProduction } from '@/features/production/useProductions';

/**
 * Phase 5 (Production運営UI §9): メンバー実績サマリー - 稽古の出欠実績と
 * チケット販売実績（Check-in基準）をメンバーごとに一覧できる、読み取り専用の
 * レポート画面。NO_SHOWは販売実績（チケット販売数）には含まれるが、実来場数
 * には含まれない既存仕様をそのまま列として分けて表示する。
 */
export default function ProductionMemberPerformanceSummaryScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const productionQuery = useProduction(id);
  const production = productionQuery.data;
  const summaryQuery = useMemberPerformanceSummary(id);

  const isPrimaryManager = !!production?.is_primary_manager;

  if (productionQuery.isLoading) {
    return <ActivityIndicator testID="member-performance-summary-loading" />;
  }

  if (!production) {
    return <ThemedText testID="member-performance-summary-not-found">この公演が見つかりません。</ThemedText>;
  }

  if (!isPrimaryManager) {
    return <ThemedText testID="member-performance-summary-forbidden">メンバー実績サマリーはPrimaryManagerのみ利用できます。</ThemedText>;
  }

  const summary = summaryQuery.data;

  return (
    <>
      <ThemedText type="title" style={styles.pageTitle}>
        メンバー実績サマリー
      </ThemedText>

      {summaryQuery.isLoading && <ActivityIndicator testID="member-performance-summary-summary-loading" />}

      {summary && summary.members.length === 0 && (
        <ThemedText testID="member-performance-summary-empty" themeColor="textSecondary">
          表示できる実績がありません。
        </ThemedText>
      )}

      {summary && summary.members.length > 0 && (
        <ScrollView horizontal testID="member-performance-summary-table-scroll">
          <View testID="member-performance-summary-table">
            <View style={[styles.row, styles.headerRow]}>
              <ThemedText type="smallBold" style={styles.colName}>
                メンバー
              </ThemedText>
              <ThemedText type="smallBold" style={styles.colNum}>
                出席
              </ThemedText>
              <ThemedText type="smallBold" style={styles.colNum}>
                欠席
              </ThemedText>
              <ThemedText type="smallBold" style={styles.colNum}>
                遅刻
              </ThemedText>
              <ThemedText type="smallBold" style={styles.colNum}>
                早退
              </ThemedText>
              <ThemedText type="smallBold" style={styles.colNum}>
                稽古回数
              </ThemedText>
              <ThemedText type="smallBold" style={styles.colNum}>
                チケット販売実績
              </ThemedText>
              <ThemedText type="smallBold" style={styles.colNum}>
                実来場数
              </ThemedText>
            </View>

            {summary.members.map((member) => (
              <View key={member.person_id} style={styles.row} testID={`member-performance-summary-row-${member.person_id}`}>
                <ThemedText style={styles.colName}>{member.display_name ?? '（未設定）'}</ThemedText>
                <ThemedText style={styles.colNum}>{member.attended_count}</ThemedText>
                <ThemedText style={styles.colNum}>{member.absent_count}</ThemedText>
                <ThemedText style={styles.colNum}>{member.late_count}</ThemedText>
                <ThemedText style={styles.colNum}>{member.early_left_count}</ThemedText>
                <ThemedText style={styles.colNum}>{member.rehearsal_count}</ThemedText>
                <ThemedText style={styles.colNum}>{member.ticket_sales_count}</ThemedText>
                <ThemedText style={styles.colNum}>{member.ticket_attendance_count}</ThemedText>
              </View>
            ))}
          </View>
        </ScrollView>
      )}

      <ThemedText type="small" themeColor="textSecondary" style={styles.note}>
        チケット販売実績はCheck-in数（NO_SHOWを含む）、実来場数はCheck-in済み（NO_SHOWを除く）の件数です。
      </ThemedText>
    </>
  );
}

const styles = StyleSheet.create({
  pageTitle: { marginBottom: Spacing.two },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: Spacing.two,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: '#eee',
    gap: Spacing.three,
  },
  headerRow: { borderBottomWidth: 1, borderBottomColor: '#ccc' },
  colName: { width: 160 },
  colNum: { width: 96, textAlign: 'right' },
  note: { marginTop: Spacing.three },
});
