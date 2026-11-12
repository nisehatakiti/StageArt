import { ActivityIndicator, StyleSheet } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';
import { getErrorMessage } from '@/utils/errorMessage';

import { formatCurrency } from './currency';
import { useProductionAccountingDetail, type AccountingCategory } from './detail';

/**
 * 公演会計の費目別の予算・実績と取引明細
 * (OrganizationAccountingAndProductionCostPolicy.md §3, AccountingUIAndReportingPolicy.md
 * "Accounting Actuals List"). Rendered by the Production 会計 screen only after it has
 * confirmed the viewer may see accounting figures.
 */
export function ProductionAccountingDetailSection({ productionId }: { productionId: string }) {
  const detailQuery = useProductionAccountingDetail(productionId, true);

  if (detailQuery.isLoading) {
    return <ActivityIndicator testID="accounting-detail-loading" />;
  }

  if (detailQuery.isError || !detailQuery.data) {
    return (
      <ThemedText testID="accounting-detail-error" themeColor="textSecondary">
        {getErrorMessage(detailQuery.error)}
      </ThemedText>
    );
  }

  const { categories, transactions, currency, has_budget: hasBudget } = detailQuery.data;
  const revenue = categories.filter((c) => c.type === 'REVENUE');
  const expense = categories.filter((c) => c.type === 'EXPENSE');

  return (
    <>
      <ThemedView style={styles.card} testID="accounting-categories">
        <ThemedText type="subtitle">費目別</ThemedText>
        {categories.length === 0 ? (
          <ThemedText type="small" themeColor="textSecondary" testID="accounting-categories-empty">
            まだありません。
          </ThemedText>
        ) : (
          <>
            <ThemedView style={styles.row}>
              <ThemedText type="small" themeColor="textSecondary" style={styles.nameCell}>
                費目
              </ThemedText>
              <ThemedText type="small" themeColor="textSecondary" style={styles.amountCell}>
                予算
              </ThemedText>
              <ThemedText type="small" themeColor="textSecondary" style={styles.amountCell}>
                実績
              </ThemedText>
            </ThemedView>
            <CategoryGroup label="収入" rows={revenue} currency={currency} hasBudget={hasBudget} />
            <CategoryGroup label="支出" rows={expense} currency={currency} hasBudget={hasBudget} />
          </>
        )}
      </ThemedView>

      <ThemedView style={styles.card} testID="accounting-transactions">
        <ThemedText type="subtitle">取引明細</ThemedText>
        {transactions.length === 0 ? (
          <ThemedText type="small" themeColor="textSecondary" testID="accounting-transactions-empty">
            まだありません。
          </ThemedText>
        ) : (
          transactions.map((t, index) => (
            <ThemedView key={`${t.journal_entry_id}-${index}`} style={styles.transaction} testID={`accounting-transaction-${index}`}>
              <ThemedView style={styles.row}>
                <ThemedText type="small" themeColor="textSecondary">
                  {t.journal_date}
                </ThemedText>
                <ThemedText type="small" themeColor="textSecondary">
                  {t.account_name}
                </ThemedText>
              </ThemedView>
              <ThemedView style={styles.row}>
                <ThemedText style={styles.nameCell}>{t.description}</ThemedText>
                <ThemedText testID={`accounting-transaction-${index}-amount`}>
                  {t.income !== null ? `収入 ${formatCurrency(t.income, currency)}` : `支出 ${formatCurrency(t.expense ?? 0, currency)}`}
                </ThemedText>
              </ThemedView>
            </ThemedView>
          ))
        )}
      </ThemedView>
    </>
  );
}

function CategoryGroup({
  label,
  rows,
  currency,
  hasBudget,
}: {
  label: string;
  rows: AccountingCategory[];
  currency: string;
  hasBudget: boolean;
}) {
  if (rows.length === 0) {
    return null;
  }

  return (
    <ThemedView style={styles.group}>
      <ThemedText type="small">{label}</ThemedText>
      {rows.map((row) => (
        <ThemedView key={row.account_id} style={styles.row} testID={`accounting-category-${row.account_id}`}>
          <ThemedText style={styles.nameCell}>{row.name}</ThemedText>
          <ThemedText style={styles.amountCell} themeColor={hasBudget ? 'text' : 'textSecondary'}>
            {row.budget === null ? '—' : formatCurrency(row.budget, currency)}
          </ThemedText>
          <ThemedText style={styles.amountCell}>{formatCurrency(row.actual, currency)}</ThemedText>
        </ThemedView>
      ))}
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
  group: { gap: Spacing.one },
  row: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: Spacing.two },
  nameCell: { flex: 1 },
  amountCell: { minWidth: 96, textAlign: 'right' },
  transaction: { gap: Spacing.half, paddingVertical: Spacing.one },
});
