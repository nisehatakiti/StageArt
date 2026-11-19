import { useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, ScrollView, StyleSheet, TouchableOpacity, View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { ThemedTextInput } from '@/components/themed-text-input';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import {
  applicantActionFor,
  canChangeApprovedAmount,
  canReviewExpense,
  EXPENSE_STATUS_LABEL,
  expenseActionErrorMessage,
  formatYen,
} from '@/features/expense/permissions';
import type { Expense, ExpenseReview } from '@/features/expense/types';
import { useApplicantExpenseAction, useApproveExpense, useExpense, useRejectExpense } from '@/features/expense/useExpenses';
import { useCurrentPerson } from '@/features/person/useCurrentPerson';
import { useProduction } from '@/features/production/useProductions';
import { confirmAlert } from '@/utils/confirmAlert';
import { getErrorMessage } from '@/utils/errorMessage';

/**
 * 修正8 経費申請の詳細 (ExpenseApprovalWorkflow.md "Approval Entry"): the
 * screen a 経費申請 notification opens, and the one the 承認待ち list
 * leads to. Shows the requested amount, the approved amount, the
 * rejection reason and the whole 申請・承認履歴.
 *
 * - An approver (PrimaryManager / ACCOUNTING_MANAGER) who is not the
 *   applicant, creator or payer sees 承認 / 却下 while it is SUBMITTED.
 *   The approved total can be changed only for a single-line Expense; a
 *   rejection needs a reason.
 * - The applicant sees 申請 / 取り下げ / 再申請 for their own Expense.
 * - Approval sends the revision on screen, so an application edited in
 *   the meantime is refused by the server (409) instead of approved.
 * Every rule is enforced again by the API.
 */
export default function ExpenseDetailScreen() {
  const { expenseId } = useLocalSearchParams<{ expenseId: string }>();
  const expenseQuery = useExpense(expenseId);
  const expense = expenseQuery.data;
  const me = useCurrentPerson().data;
  const production = useProduction(expense?.production_id).data;

  if (expenseQuery.isLoading) {
    return <ActivityIndicator testID="expense-loading" />;
  }

  if (expenseQuery.isError || !expense) {
    return (
      <ThemedText testID="expense-error" style={styles.page}>
        {getErrorMessage(expenseQuery.error)}
      </ThemedText>
    );
  }

  const canReview = canReviewExpense(production, expense, me?.id);
  const applicantAction = applicantActionFor(expense, me?.id);

  return (
    <ScrollView contentContainerStyle={styles.page} testID="expense-detail">
      <ThemedText type="title" style={styles.pageTitle}>
        経費申請
      </ThemedText>

      <View style={styles.summary}>
        <Row label="状態" value={EXPENSE_STATUS_LABEL[expense.status]} testID="expense-status" />
        <Row label="申請額" value={formatYen(expense.requested_amount ?? expense.total_amount)} testID="expense-requested-amount" />
        {expense.approved_amount !== null && (
          <Row label="承認額" value={formatYen(expense.approved_amount)} testID="expense-approved-amount" />
        )}
        {expense.status === 'REJECTED' && expense.rejection_reason && (
          <Row label="却下理由" value={expense.rejection_reason} testID="expense-rejection-reason" />
        )}
      </View>

      {expense.status === 'CONFIRMED' && (
        <ThemedText type="small" themeColor="textSecondary" testID="expense-legacy-note">
          以前の確定方式で登録された経費です。会計担当者による個別確認の対象で、この画面からは操作できません。
        </ThemedText>
      )}

      <ThemedText type="subtitle" style={styles.sectionTitle}>
        明細
      </ThemedText>
      <View style={styles.list}>
        {expense.lines.map((line) => (
          <View key={line.id} style={styles.row} testID={`expense-line-${line.id}`}>
            <ThemedText style={styles.grow}>{line.description || '（内容なし）'}</ThemedText>
            <ThemedText>{formatYen(line.amount)}</ThemedText>
          </View>
        ))}
      </View>

      {/* Keyed by revision: an application edited by the applicant starts
          a fresh review form with its new requested amount. */}
      {canReview && <ReviewActions key={expense.revision} expense={expense} />}
      {applicantAction && <ApplicantAction expense={expense} action={applicantAction} />}

      <ThemedText type="subtitle" style={styles.sectionTitle}>
        申請・承認履歴
      </ThemedText>
      <View style={styles.list} testID="expense-history">
        {expense.reviews.length === 0 && (
          <ThemedText type="small" themeColor="textSecondary">
            まだ申請されていません。
          </ThemedText>
        )}
        {expense.reviews.map((review, index) => (
          <HistoryRow key={`${review.revision}-${review.action}-${index}`} review={review} index={index} />
        ))}
      </View>
    </ScrollView>
  );
}

function ReviewActions({ expense }: { expense: Expense }) {
  const requested = expense.requested_amount ?? expense.total_amount;
  const [amountText, setAmountText] = useState(String(requested));
  const [reason, setReason] = useState('');
  const [error, setError] = useState<string | null>(null);
  const approve = useApproveExpense(expense.id);
  const reject = useRejectExpense(expense.id);
  const amountEditable = canChangeApprovedAmount(expense);
  const busy = approve.isPending || reject.isPending;
  // Validated as the approver types: 0 or less, or a non-integer, is never
  // sent (the server refuses it too).
  const parsedAmount = amountEditable ? Number(amountText.trim()) : requested;
  const amountError = Number.isInteger(parsedAmount) && parsedAmount > 0 ? null : '承認額は1円以上の整数で入力してください。';
  const reasonMissing = reason.trim() === '';

  function submitApproval() {
    setError(null);
    const amount = parsedAmount;

    if (amountError) {
      return;
    }

    confirmAlert('経費申請の承認', `承認額 ${formatYen(amount)} で承認し、会計に計上します。よろしいですか？`, [
      { text: 'キャンセル', style: 'cancel' },
      {
        text: '承認する',
        style: 'default',
        onPress: () => {
          approve
            .mutateAsync({ revision: expense.revision, approvedAmount: amount === requested ? undefined : amount })
            .catch((caught: unknown) => setError(expenseActionErrorMessage(caught)));
        },
      },
    ]);
  }

  function submitRejection() {
    setError(null);

    if (reasonMissing) {
      return;
    }

    reject.mutateAsync(reason.trim()).catch((caught: unknown) => setError(expenseActionErrorMessage(caught)));
  }

  return (
    <View style={styles.actions} testID="expense-review-actions">
      <ThemedText type="subtitle" style={styles.sectionTitle}>
        承認・却下
      </ThemedText>

      <ThemedText type="small">承認額（円）</ThemedText>
      {amountEditable ? (
        <ThemedTextInput
          value={amountText}
          onChangeText={setAmountText}
          keyboardType="number-pad"
          style={styles.input}
          testID="expense-approved-amount-input"
          accessibilityLabel="承認額"
        />
      ) : (
        <>
          <ThemedText testID="expense-approved-amount-fixed">{formatYen(requested)}</ThemedText>
          <ThemedText type="small" themeColor="textSecondary">
            明細が複数あるため、承認額は申請額のみ指定できます。
          </ThemedText>
        </>
      )}

      {amountError && (
        <ThemedText style={styles.error} testID="expense-amount-error">
          {amountError}
        </ThemedText>
      )}

      <TouchableOpacity
        onPress={submitApproval}
        disabled={busy || !!amountError}
        style={[styles.button, (busy || !!amountError) && styles.buttonDisabled]}
        testID="expense-approve"
        accessibilityRole="button"
        accessibilityState={{ disabled: busy || !!amountError }}
      >
        <ThemedText style={styles.buttonText}>承認して計上する</ThemedText>
      </TouchableOpacity>

      <ThemedText type="small" style={styles.fieldLabel}>
        却下理由（却下する場合は必須）
      </ThemedText>
      <ThemedTextInput
        value={reason}
        onChangeText={setReason}
        multiline
        style={[styles.input, styles.textarea]}
        testID="expense-reject-reason-input"
        accessibilityLabel="却下理由"
      />
      <TouchableOpacity
        onPress={submitRejection}
        disabled={busy || reasonMissing}
        style={[styles.secondaryButton, (busy || reasonMissing) && styles.buttonDisabled]}
        testID="expense-reject"
        accessibilityRole="button"
        accessibilityState={{ disabled: busy || reasonMissing }}
      >
        <ThemedText style={styles.secondaryButtonText}>却下する</ThemedText>
      </TouchableOpacity>

      {error && (
        <ThemedText style={styles.error} testID="expense-action-error">
          {error}
        </ThemedText>
      )}
    </View>
  );
}

const APPLICANT_ACTION_LABEL = { submit: '申請する', withdraw: '申請を取り下げる', resubmit: '再申請する' } as const;

function ApplicantAction({ expense, action }: { expense: Expense; action: 'submit' | 'withdraw' | 'resubmit' }) {
  const mutation = useApplicantExpenseAction(expense.id);
  const [error, setError] = useState<string | null>(null);

  function run() {
    setError(null);
    mutation.mutateAsync(action).catch((caught: unknown) => setError(expenseActionErrorMessage(caught)));
  }

  return (
    <View style={styles.actions} testID="expense-applicant-actions">
      <TouchableOpacity
        onPress={run}
        disabled={mutation.isPending}
        style={[action === 'withdraw' ? styles.secondaryButton : styles.button, mutation.isPending && styles.buttonDisabled]}
        testID={`expense-${action}`}
        accessibilityRole="button"
      >
        <ThemedText style={action === 'withdraw' ? styles.secondaryButtonText : styles.buttonText}>{APPLICANT_ACTION_LABEL[action]}</ThemedText>
      </TouchableOpacity>
      {error && (
        <ThemedText style={styles.error} testID="expense-applicant-error">
          {error}
        </ThemedText>
      )}
    </View>
  );
}

const REVIEW_ACTION_LABEL: Record<ExpenseReview['action'], string> = {
  SUBMITTED: '申請',
  WITHDRAWN: '取り下げ',
  APPROVED: '承認',
  REJECTED: '却下',
  MIGRATED: '旧データから移行（承認済み）',
};

function HistoryRow({ review, index }: { review: ExpenseReview; index: number }) {
  return (
    <View style={styles.historyRow} testID={`expense-history-${index}`}>
      <ThemedText>
        第{review.revision}回 {REVIEW_ACTION_LABEL[review.action]}
      </ThemedText>
      <ThemedText type="small" themeColor="textSecondary">
        {review.acted_at.slice(0, 16).replace('T', ' ')}
        {review.requested_amount !== null ? `／申請額 ${formatYen(review.requested_amount)}` : ''}
        {review.approved_amount !== null ? `／承認額 ${formatYen(review.approved_amount)}` : ''}
      </ThemedText>
      {review.reason && <ThemedText type="small">理由：{review.reason}</ThemedText>}
    </View>
  );
}

function Row({ label, value, testID }: { label: string; value: string; testID: string }) {
  return (
    <View style={styles.row} testID={testID}>
      <ThemedText>{label}</ThemedText>
      <ThemedText style={styles.value} testID={`${testID}-value`}>
        {value}
      </ThemedText>
    </View>
  );
}

const styles = StyleSheet.create({
  page: { padding: Spacing.four, gap: Spacing.two },
  pageTitle: { marginBottom: Spacing.two },
  sectionTitle: { marginTop: Spacing.three, marginBottom: Spacing.one },
  summary: { gap: Spacing.one },
  list: { gap: Spacing.one },
  row: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    gap: Spacing.two,
    paddingVertical: Spacing.one,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: '#eee',
  },
  grow: { flex: 1 },
  value: { flexShrink: 1, textAlign: 'right' },
  historyRow: { paddingVertical: Spacing.one, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: '#eee' },
  actions: { gap: Spacing.two, marginTop: Spacing.two },
  fieldLabel: { marginTop: Spacing.two },
  input: { borderWidth: 1, borderColor: '#d7d2ca', borderRadius: Radius.medium, paddingHorizontal: Spacing.two, paddingVertical: Spacing.one },
  textarea: { minHeight: 80 },
  button: {
    backgroundColor: BrandColors.warmAmber,
    borderRadius: Radius.medium,
    paddingVertical: Spacing.two,
    paddingHorizontal: Spacing.three,
    alignItems: 'center',
    alignSelf: 'flex-start',
  },
  buttonText: { color: '#fff', fontWeight: '600' },
  secondaryButton: {
    borderWidth: 1,
    borderColor: BrandColors.warmAmber,
    borderRadius: Radius.medium,
    paddingVertical: Spacing.two,
    paddingHorizontal: Spacing.three,
    alignSelf: 'flex-start',
  },
  secondaryButtonText: { color: BrandColors.warmAmber, fontWeight: '600' },
  buttonDisabled: { opacity: 0.5 },
  error: { color: '#a6483a', marginTop: Spacing.one },
});
