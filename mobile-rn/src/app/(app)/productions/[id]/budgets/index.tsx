import { useLocalSearchParams, useRouter, type Href } from 'expo-router';
import { ActivityIndicator, ScrollView, StyleSheet, TouchableOpacity, View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import { BUDGET_STATUS_LABEL } from '@/features/budget/form';
import { useProductionBudgets } from '@/features/budget/useBudgets';
import { getErrorMessage } from '@/utils/errorMessage';

/**
 * 予算の一覧 (第1スプリント 予算策定): the Production's Budgets
 * (Budget.md "Multiple Budgets" - e.g. A会場案 / B会場案), newest first,
 * each opening its own input screen. Opened from the 会計 tab. Only the
 * PrimaryManager or an Accounting Manager is answered by the API (403
 * otherwise, shown as the refusal message).
 */
export default function ProductionBudgetsScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();
  const budgetsQuery = useProductionBudgets(id);

  if (budgetsQuery.isLoading) {
    return <ActivityIndicator testID="budgets-loading" />;
  }

  if (budgetsQuery.isError) {
    return (
      <ThemedText testID="budgets-error" style={styles.page}>
        {getErrorMessage(budgetsQuery.error)}
      </ThemedText>
    );
  }

  const budgets = [...(budgetsQuery.data ?? [])].sort((a, b) => b.updated_at.localeCompare(a.updated_at));

  return (
    <ScrollView contentContainerStyle={styles.page}>
      <ThemedText type="title" style={styles.pageTitle}>
        予算
      </ThemedText>
      <ThemedText type="small" themeColor="textSecondary">
        公演でかかる費用と見込める収入の計画です。案ごとに複数作れます。
      </ThemedText>

      <TouchableOpacity
        onPress={() => router.push(`/productions/${id}/budgets/new` as Href)}
        style={styles.button}
        testID="budgets-create"
        accessibilityRole="button"
      >
        <ThemedText style={styles.buttonText}>新しい予算を作る</ThemedText>
      </TouchableOpacity>

      {budgets.length === 0 && (
        <ThemedText type="small" themeColor="textSecondary" testID="budgets-empty">
          まだ予算はありません。
        </ThemedText>
      )}

      <View style={styles.list}>
        {budgets.map((budget) => (
          <TouchableOpacity
            key={budget.id}
            onPress={() => router.push(`/productions/${id}/budgets/${budget.id}` as Href)}
            style={styles.row}
            testID={`budget-row-${budget.id}`}
            accessibilityRole="button"
          >
            <View style={styles.grow}>
              <ThemedText numberOfLines={1}>{budget.name}</ThemedText>
              <ThemedText type="small" themeColor="textSecondary">
                {BUDGET_STATUS_LABEL[budget.status]}／更新 {budget.updated_at.slice(0, 10)}
              </ThemedText>
            </View>
            <ThemedText type="link">開く</ThemedText>
          </TouchableOpacity>
        ))}
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  page: { padding: Spacing.four, gap: Spacing.two },
  pageTitle: { marginBottom: Spacing.one },
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
  button: {
    backgroundColor: BrandColors.warmAmber,
    borderRadius: Radius.medium,
    paddingVertical: Spacing.two,
    paddingHorizontal: Spacing.three,
    alignItems: 'center',
    alignSelf: 'flex-start',
  },
  buttonText: { color: '#fff', fontWeight: '600' },
});
