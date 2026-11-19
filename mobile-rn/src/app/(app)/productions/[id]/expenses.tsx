import { useLocalSearchParams, useRouter, type Href } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, ScrollView, StyleSheet, TouchableOpacity, View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import { EXPENSE_STATUS_LABEL, formatYen } from '@/features/expense/permissions';
import type { ExpenseStatus } from '@/features/expense/types';
import { useProductionExpenses } from '@/features/expense/useExpenses';
import { canViewProductionAccounting } from '@/features/production/permissions';
import { useProduction } from '@/features/production/useProductions';
import { getErrorMessage } from '@/utils/errorMessage';

/**
 * 修正8 経費申請 (承認待ち): ExpenseApprovalWorkflow.md "Approval Entry /
 * Manager Review" - the manager-menu entry that lists pending Expense
 * applications without depending on notifications. Same approval as the
 * notification route: each row opens the Expense's own detail screen.
 *
 * Listed only for the PrimaryManager or an ACCOUNTING_MANAGER; nothing is
 * fetched for anyone else, and the API refuses them on its own.
 */
const FILTERS: { key: ExpenseStatus | 'ALL'; label: string }[] = [
  { key: 'SUBMITTED', label: '承認待ち' },
  { key: 'ALL', label: 'すべて' },
];

export default function ProductionExpensesScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();
  const productionQuery = useProduction(id);
  const allowed = canViewProductionAccounting(productionQuery.data);
  const [filter, setFilter] = useState<ExpenseStatus | 'ALL'>('SUBMITTED');
  const expensesQuery = useProductionExpenses(id, filter === 'ALL' ? undefined : filter, allowed);

  if (productionQuery.isLoading) {
    return <ActivityIndicator testID="production-expenses-loading" />;
  }

  if (!allowed) {
    return (
      <ThemedText testID="production-expenses-forbidden" style={styles.page}>
        経費申請の確認はPrimaryManagerまたは会計担当の権限を持つ担当者のみ利用できます。
      </ThemedText>
    );
  }

  const expenses = expensesQuery.data ?? [];

  return (
    <ScrollView contentContainerStyle={styles.page}>
      <ThemedText type="title" style={styles.pageTitle}>
        経費申請
      </ThemedText>

      <View style={styles.filters}>
        {FILTERS.map((option) => (
          <TouchableOpacity
            key={option.key}
            onPress={() => setFilter(option.key)}
            style={[styles.filter, filter === option.key && styles.filterActive]}
            testID={`production-expenses-filter-${option.key}`}
            accessibilityRole="button"
            accessibilityState={{ selected: filter === option.key }}
          >
            <ThemedText style={filter === option.key ? styles.filterActiveText : undefined}>{option.label}</ThemedText>
          </TouchableOpacity>
        ))}
      </View>

      {expensesQuery.isLoading && <ActivityIndicator testID="production-expenses-list-loading" />}
      {expensesQuery.isError && (
        <ThemedText testID="production-expenses-error" style={styles.error}>
          {getErrorMessage(expensesQuery.error)}
        </ThemedText>
      )}
      {!expensesQuery.isLoading && !expensesQuery.isError && expenses.length === 0 && (
        <ThemedText type="small" themeColor="textSecondary" testID="production-expenses-empty">
          {filter === 'SUBMITTED' ? '承認待ちの経費申請はありません。' : '経費申請はありません。'}
        </ThemedText>
      )}

      <View style={styles.list}>
        {expenses.map((expense) => (
          <TouchableOpacity
            key={expense.id}
            onPress={() => router.push(`/expenses/${expense.id}` as Href)}
            style={styles.row}
            testID={`production-expense-row-${expense.id}`}
            accessibilityRole="button"
          >
            <View style={styles.grow}>
              <ThemedText numberOfLines={1}>{expense.lines[0]?.description || '経費申請'}</ThemedText>
              <ThemedText type="small" themeColor="textSecondary">
                {EXPENSE_STATUS_LABEL[expense.status]}
                {expense.submitted_at ? `／申請日 ${expense.submitted_at.slice(0, 10)}` : ''}
              </ThemedText>
            </View>
            <ThemedText>{formatYen(expense.requested_amount ?? expense.total_amount)}</ThemedText>
          </TouchableOpacity>
        ))}
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  page: { padding: Spacing.four, gap: Spacing.two },
  pageTitle: { marginBottom: Spacing.two },
  filters: { flexDirection: 'row', gap: Spacing.two },
  filter: {
    borderWidth: 1,
    borderColor: BrandColors.warmAmber,
    borderRadius: Radius.medium,
    paddingVertical: Spacing.one,
    paddingHorizontal: Spacing.three,
  },
  filterActive: { backgroundColor: BrandColors.warmAmber },
  filterActiveText: { color: '#fff', fontWeight: '600' },
  list: { gap: Spacing.one },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: Spacing.two,
    paddingVertical: Spacing.two,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: '#eee',
  },
  grow: { flex: 1 },
  error: { color: '#a6483a' },
});
