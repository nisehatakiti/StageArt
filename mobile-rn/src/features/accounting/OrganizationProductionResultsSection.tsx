import { useRouter, type Href } from 'expo-router';
import { ActivityIndicator, StyleSheet, TouchableOpacity } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';
import { getErrorMessage } from '@/utils/errorMessage';

import { formatCurrency } from './currency';
import { useOrganizationProductionResults } from './organizationResults';

/**
 * 団体会計の公演別実績 (OrganizationAccountingAndProductionCostPolicy.md §2):
 * 売上 / 原価 / 収支 / 決算状態 per Production and the totals. The 費目別
 * detail is the Production's own 会計 screen, which each row opens.
 */
export function OrganizationProductionResultsSection({ organizationId }: { organizationId: string }) {
  const router = useRouter();
  const resultsQuery = useOrganizationProductionResults(organizationId);

  if (resultsQuery.isLoading) {
    return <ActivityIndicator testID="organization-production-results-loading" />;
  }

  if (resultsQuery.isError || !resultsQuery.data) {
    return (
      <ThemedText testID="organization-production-results-error" themeColor="textSecondary">
        {getErrorMessage(resultsQuery.error)}
      </ThemedText>
    );
  }

  const { productions, totals, currency } = resultsQuery.data;

  return (
    <ThemedView style={styles.card} testID="organization-production-results">
      <ThemedText type="subtitle">公演別実績</ThemedText>
      {productions.length === 0 ? (
        <ThemedText type="small" themeColor="textSecondary" testID="organization-production-results-empty">
          公演がありません。
        </ThemedText>
      ) : (
        <>
          {productions.map((p) => (
            <TouchableOpacity
              key={p.production_id}
              onPress={() => router.push(`/production/${p.production_id}/accounting` as Href)}
              accessibilityRole="button"
              testID={`organization-production-result-${p.production_id}`}
              style={styles.production}
            >
              <ThemedView style={styles.row}>
                <ThemedText type="link" style={styles.name}>
                  {p.name}
                </ThemedText>
                <ThemedText type="small" themeColor="textSecondary">
                  {p.closing_confirmed ? '確定済み' : '確定前'}
                </ThemedText>
              </ThemedView>
              <Figures revenue={p.revenue} cost={p.cost} profit={p.profit} currency={currency} />
            </TouchableOpacity>
          ))}
          <ThemedView style={[styles.production, styles.total]} testID="organization-production-results-total">
            <ThemedText type="default">合計</ThemedText>
            <Figures revenue={totals.revenue} cost={totals.cost} profit={totals.profit} currency={currency} />
          </ThemedView>
        </>
      )}
    </ThemedView>
  );
}

function Figures({ revenue, cost, profit, currency }: { revenue: number; cost: number; profit: number; currency: string }) {
  return (
    <ThemedView style={styles.row}>
      <ThemedText type="small">売上 {formatCurrency(revenue, currency)}</ThemedText>
      <ThemedText type="small">原価 {formatCurrency(cost, currency)}</ThemedText>
      <ThemedText type="small">収支 {formatCurrency(profit, currency)}</ThemedText>
    </ThemedView>
  );
}

const styles = StyleSheet.create({
  card: {
    borderWidth: 1,
    borderColor: '#e1dee6',
    borderRadius: 10,
    padding: Spacing.three,
    gap: Spacing.two,
  },
  production: { gap: Spacing.one, paddingVertical: Spacing.one },
  total: { borderTopWidth: 1, borderTopColor: '#e1dee6', paddingTop: Spacing.two },
  row: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: Spacing.two, flexWrap: 'wrap' },
  name: { flex: 1 },
});
