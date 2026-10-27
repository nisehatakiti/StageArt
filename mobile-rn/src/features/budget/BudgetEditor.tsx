import { useRouter, type Href } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, ScrollView, StyleSheet, TouchableOpacity, View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { ThemedTextInput } from '@/components/themed-text-input';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import { getErrorMessage } from '@/utils/errorMessage';

import {
  BUDGET_STATUS_LABEL,
  buildFormRows,
  computeBudgetTotals,
  formatYenAmount,
  parseAmountText,
  toBudgetItemsPayload,
  validateBudgetForm,
  type BudgetFormRow,
} from './form';
import type { Budget, BudgetItem } from './types';
import { useBudget, useBudgetItems, useSaveBudget } from './useBudgets';

/**
 * 予算の入力 (BudgetManagementPolicy.md "Budget Input Screen"): plain
 * 予算項目 with an amount each, grouped as 収入の見込み / 支出の見込み,
 * with 収入合計・支出合計・計画収支 updated while typing. No Account is
 * picked here - each item is a preset Account behind the scenes.
 *
 * budgetId undefined: a new Budget (the first save creates it and opens
 * its own URL). Only a DRAFT Budget is editable (Budget.md "Draft").
 */
export function BudgetEditor({
  productionId,
  budgetId,
  justCreated = false,
}: {
  productionId: string;
  budgetId?: string;
  /** Opened right after the first save (?saved=1). */
  justCreated?: boolean;
}) {
  const itemsQuery = useBudgetItems(productionId);
  const budgetQuery = useBudget(budgetId);
  // updated_at of the copy the last save returned, so 保存しました stays
  // visible on the form that restarts from it.
  const [savedVersion, setSavedVersion] = useState<string | null>(null);
  const loadError = itemsQuery.error ?? budgetQuery.error;

  if (loadError) {
    return (
      <ThemedText testID="budget-load-error" style={styles.page}>
        {getErrorMessage(loadError)}
      </ThemedText>
    );
  }

  if (!itemsQuery.data || (budgetId && !budgetQuery.data)) {
    return <ActivityIndicator testID="budget-loading" />;
  }

  // The form starts from the server's copy; a saved Budget comes back with
  // a new updated_at, which starts the form again from what was stored.
  return (
    <BudgetForm
      key={budgetQuery.data?.updated_at ?? 'new'}
      productionId={productionId}
      budgetId={budgetId}
      items={itemsQuery.data}
      budget={budgetQuery.data}
      justSaved={justCreated || (!!savedVersion && savedVersion === budgetQuery.data?.updated_at)}
      onSaved={setSavedVersion}
    />
  );
}

function BudgetForm({
  productionId,
  budgetId,
  items,
  budget,
  justSaved,
  onSaved,
}: {
  productionId: string;
  budgetId?: string;
  items: BudgetItem[];
  budget?: Budget;
  justSaved: boolean;
  onSaved: (updatedAt: string) => void;
}) {
  const router = useRouter();
  const save = useSaveBudget(productionId, budgetId);

  const [initial] = useState(() => buildFormRows(items, budget));
  const [name, setName] = useState(budget?.name ?? '');
  const [rows, setRows] = useState<BudgetFormRow[]>(initial.rows);
  const [errors, setErrors] = useState<string[]>([]);
  const [saved, setSaved] = useState(justSaved);
  const unmatchedLines = initial.unmatchedLines;

  const editable = !budget || budget.status === 'DRAFT';
  const totals = computeBudgetTotals(rows);

  const setAmount = (key: string, amountText: string) => {
    setSaved(false);
    setRows((current) => current.map((row) => (row.key === key ? { ...row, amountText } : row)));
  };

  const onSave = () => {
    const found = validateBudgetForm(name, rows);
    setErrors(found);
    setSaved(false);

    if (found.length > 0) {
      return;
    }

    // mutateAsync, not per-call callbacks: saving an existing Budget
    // restarts this form from the stored copy (new key), and the result
    // must still reach the parent.
    save
      .mutateAsync({ name: name.trim(), items: toBudgetItemsPayload(rows) })
      .then((result) => {
        onSaved(result.updated_at);
        setSaved(true);

        if (!budgetId) {
          router.replace(`/productions/${productionId}/budgets/${result.id}?saved=1` as Href);
        }
      })
      .catch(() => {
        // Shown from save.isError.
      });
  };

  return (
    <ScrollView contentContainerStyle={styles.page} testID="budget-editor">
      <TouchableOpacity onPress={() => router.push(`/productions/${productionId}/budgets` as Href)} testID="budget-back-to-list" accessibilityRole="link">
        <ThemedText type="link">← 予算の一覧</ThemedText>
      </TouchableOpacity>

      <ThemedText type="title" style={styles.pageTitle}>
        {budget ? '予算の編集' : '新しい予算'}
      </ThemedText>
      <ThemedText type="small" themeColor="textSecondary">
        公演でかかりそうな費用と、見込める収入を項目ごとに入力してください。使わない項目は空欄のままで構いません。
      </ThemedText>

      {budget && (
        <ThemedText type="small" themeColor="textSecondary" testID="budget-status">
          状態：{BUDGET_STATUS_LABEL[budget.status]}
        </ThemedText>
      )}

      <ThemedText style={styles.fieldLabel}>予算名</ThemedText>
      <ThemedTextInput
        testID="budget-name"
        value={name}
        onChangeText={(text) => {
          setSaved(false);
          setName(text);
        }}
        editable={editable}
        placeholder="例：河童ホームラン2026予算 Version1"
        style={styles.input}
      />

      <ItemSection title="収入の見込み" type="REVENUE" rows={rows} editable={editable} onChange={setAmount} />
      <ItemSection title="支出の見込み" type="EXPENSE" rows={rows} editable={editable} onChange={setAmount} />

      <View style={styles.summary} testID="budget-summary">
        <SummaryRow label="収入合計" value={totals.revenue} testID="budget-total-revenue" />
        <SummaryRow label="支出合計" value={totals.expense} testID="budget-total-expense" />
        <SummaryRow label="計画収支" value={totals.balance} testID="budget-total-balance" />
      </View>

      {unmatchedLines > 0 && (
        <ThemedText style={styles.error} testID="budget-unmatched-lines">
          この予算には、この画面で扱えない項目が{unmatchedLines}件あります。保存すると失われるため、この画面からは保存できません。
        </ThemedText>
      )}

      {errors.map((error) => (
        <ThemedText key={error} style={styles.error} testID="budget-form-error">
          {error}
        </ThemedText>
      ))}
      {save.isError && (
        <ThemedText style={styles.error} testID="budget-save-error">
          保存できませんでした。{getErrorMessage(save.error)}
        </ThemedText>
      )}
      {saved && !save.isPending && (
        <ThemedText testID="budget-saved">保存しました。</ThemedText>
      )}

      {editable ? (
        <TouchableOpacity
          onPress={onSave}
          disabled={save.isPending || unmatchedLines > 0}
          style={[styles.button, (save.isPending || unmatchedLines > 0) && styles.buttonDisabled]}
          testID="budget-save"
          accessibilityRole="button"
        >
          <ThemedText style={styles.buttonText}>{save.isPending ? '保存中…' : '保存する'}</ThemedText>
        </TouchableOpacity>
      ) : (
        <ThemedText type="small" themeColor="textSecondary" testID="budget-read-only">
          {budget ? BUDGET_STATUS_LABEL[budget.status] : ''}の予算は編集できません。
        </ThemedText>
      )}

      <ThemedText type="small" themeColor="textSecondary">
        保存した予算は下書きです。会計の「予算」欄には、採用した予算だけが反映されます（採用の操作は今後追加します）。
      </ThemedText>
    </ScrollView>
  );
}

function ItemSection({
  title,
  type,
  rows,
  editable,
  onChange,
}: {
  title: string;
  type: BudgetFormRow['type'];
  rows: BudgetFormRow[];
  editable: boolean;
  onChange: (key: string, amountText: string) => void;
}) {
  return (
    <View style={styles.section} testID={`budget-section-${type}`}>
      <ThemedText type="subtitle" style={styles.sectionTitle}>
        {title}
      </ThemedText>
      {rows
        .filter((row) => row.type === type)
        .map((row) => {
          const invalid = parseAmountText(row.amountText) === null;

          return (
            <View key={row.key} style={styles.row} testID={`budget-item-${row.key}`}>
              <View style={styles.grow}>
                <ThemedText>{row.label}</ThemedText>
                {!!row.hint && (
                  <ThemedText type="small" themeColor="textSecondary">
                    {row.hint}
                  </ThemedText>
                )}
              </View>
              <ThemedTextInput
                testID={`budget-item-${row.key}-amount`}
                value={row.amountText}
                onChangeText={(text) => onChange(row.key, text)}
                editable={editable && row.editable}
                keyboardType="number-pad"
                inputMode="numeric"
                placeholder="0"
                accessibilityLabel={`${row.label}の金額`}
                style={[styles.input, styles.amountInput, invalid && styles.inputInvalid]}
              />
              <ThemedText>円</ThemedText>
            </View>
          );
        })}
    </View>
  );
}

function SummaryRow({ label, value, testID }: { label: string; value: number; testID: string }) {
  return (
    <View style={styles.summaryRow} testID={testID}>
      <ThemedText>{label}</ThemedText>
      <ThemedText testID={`${testID}-value`}>{formatYenAmount(value)}</ThemedText>
    </View>
  );
}

const styles = StyleSheet.create({
  page: { padding: Spacing.four, gap: Spacing.two },
  pageTitle: { marginBottom: Spacing.one },
  fieldLabel: { marginTop: Spacing.two },
  section: { gap: Spacing.one, marginTop: Spacing.three },
  sectionTitle: { marginBottom: Spacing.one },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: Spacing.two,
    paddingVertical: Spacing.one,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: '#eee',
  },
  grow: { flex: 1 },
  input: { borderWidth: 1, borderColor: '#d7d2ca', borderRadius: Radius.medium, paddingHorizontal: Spacing.two, paddingVertical: Spacing.one },
  amountInput: { width: 140, textAlign: 'right' },
  inputInvalid: { borderColor: '#a6483a' },
  summary: {
    marginTop: Spacing.three,
    borderWidth: 1,
    borderColor: '#e1dee6',
    borderRadius: 10,
    padding: Spacing.three,
    gap: Spacing.one,
  },
  summaryRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  error: { color: '#a6483a' },
  button: {
    backgroundColor: BrandColors.warmAmber,
    borderRadius: Radius.medium,
    paddingVertical: Spacing.two,
    paddingHorizontal: Spacing.three,
    alignItems: 'center',
    alignSelf: 'flex-start',
    marginTop: Spacing.two,
  },
  buttonText: { color: '#fff', fontWeight: '600' },
  buttonDisabled: { opacity: 0.5 },
});
