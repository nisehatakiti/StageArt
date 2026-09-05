import { useLocalSearchParams } from 'expo-router';
import { ActivityIndicator, RefreshControl, ScrollView, StyleSheet, TouchableOpacity } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';
import { useProductionAccounting } from '@/features/accounting/useProductionAccounting';
import { buildAccountingViewModel } from '@/features/accounting/viewModel';
import { getErrorMessage } from '@/utils/errorMessage';

/**
 * Phase 5.5: connects the Phase 5.4 placeholder to the real Backend API
 * (GET /productions/{id}/accounting, Backend Phase 6.0). Shows 予算/実績/
 * 差異 sourced entirely from the server - no client-side computation of
 * Variance, no guessing of an Organization Accounting Activation state
 * the Backend does not expose (see this Phase's report's Open Items).
 */
export default function AccountingScreen() {
  const { id: productionId } = useLocalSearchParams<{ id: string }>();
  const accountingQuery = useProductionAccounting(productionId);
  const viewModel = accountingQuery.data ? buildAccountingViewModel(accountingQuery.data) : null;

  return (
    <SafeAreaView style={styles.safeArea}>
      {accountingQuery.isLoading && (
        <ThemedView style={styles.centered}>
          <ActivityIndicator testID="accounting-loading" />
        </ThemedView>
      )}

      {accountingQuery.isError && (
        <ThemedView style={styles.centered}>
          <ThemedText testID="accounting-error">{getErrorMessage(accountingQuery.error)}</ThemedText>
          <TouchableOpacity
            onPress={() => accountingQuery.refetch()}
            testID="accounting-retry"
            accessibilityRole="button"
            accessibilityLabel="再読み込み"
          >
            <ThemedText type="link">再読み込み</ThemedText>
          </TouchableOpacity>
        </ThemedView>
      )}

      {!accountingQuery.isLoading && !accountingQuery.isError && viewModel && (
        <ScrollView
          contentContainerStyle={styles.content}
          refreshControl={
            <RefreshControl
              refreshing={accountingQuery.isFetching}
              onRefresh={accountingQuery.refetch}
              testID="accounting-refresh-control"
            />
          }
        >
          <ThemedText type="title" style={styles.title}>
            会計
          </ThemedText>
          <ThemedText type="small" themeColor="textSecondary">
            このProductionの予算・実績・差異です。
          </ThemedText>

          <ThemedView style={styles.summaryCard}>
            <ThemedText type="subtitle" style={styles.summaryTitle}>
              会計概要
            </ThemedText>

            <SummaryRow testID="accounting-budget-row" label="予算" row={viewModel.budget} />
            <SummaryRow testID="accounting-actual-row" label="実績" row={viewModel.actual} />
            <SummaryRow testID="accounting-variance-row" label="差異" row={viewModel.variance} />
          </ThemedView>
        </ScrollView>
      )}
    </SafeAreaView>
  );
}

function SummaryRow({
  label,
  testID,
  row,
}: {
  label: string;
  testID: string;
  row: { display: string; hasValue: boolean };
}) {
  return (
    <ThemedView style={styles.summaryRow} testID={testID}>
      <ThemedText type="default">{label}</ThemedText>
      <ThemedText
        type={row.hasValue ? 'default' : 'small'}
        themeColor={row.hasValue ? 'text' : 'textSecondary'}
        testID={`${testID}-value`}
      >
        {row.display}
      </ThemedText>
    </ThemedView>
  );
}

const styles = StyleSheet.create({
  safeArea: { flex: 1 },
  centered: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: Spacing.four, gap: Spacing.two },
  content: { flexGrow: 1, padding: Spacing.four, gap: Spacing.three },
  title: { fontSize: 24, lineHeight: 30 },
  summaryCard: {
    borderWidth: 1,
    borderColor: '#e1dee6',
    borderRadius: 10,
    padding: Spacing.three,
    gap: Spacing.two,
  },
  summaryTitle: { marginBottom: Spacing.one },
  summaryRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: Spacing.one,
  },
});
