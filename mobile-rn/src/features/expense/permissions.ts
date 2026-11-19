import { ApiError } from '@/api/errors';
import { canViewProductionAccounting } from '@/features/production/permissions';
import type { Production } from '@/types/api';
import { getErrorMessage } from '@/utils/errorMessage';

import type { Expense, ExpenseStatus } from './types';

/**
 * Which Expense actions the UI offers, mirroring the server's rules
 * (ApproveExpenseUseCase / RejectExpenseUseCase /
 * ApplicantExpenseTransitionUseCase). Only decides what is shown: the
 * API checks every request on its own.
 */
type ProductionAccess = Pick<Production, 'is_primary_manager' | 'delegate_roles'> | null | undefined;

/** The Person who may not approve this Expense: its applicant, creator or payer. */
export function isOwnExpense(expense: Expense, personId: string | undefined): boolean {
  if (!personId) {
    return true;
  }

  return expense.created_by === personId || expense.submitted_by === personId || expense.payer_person_id === personId;
}

/** 承認・却下 - PrimaryManager or ACCOUNTING_MANAGER, never on one's own Expense, only while SUBMITTED. */
export function canReviewExpense(production: ProductionAccess, expense: Expense, personId: string | undefined): boolean {
  return expense.status === 'SUBMITTED' && canViewProductionAccounting(production) && !isOwnExpense(expense, personId);
}

/** Submit / withdraw / resubmit belong to the Expense's creator (the applicant). */
export function applicantActionFor(expense: Expense, personId: string | undefined): 'submit' | 'withdraw' | 'resubmit' | null {
  if (!personId || expense.created_by !== personId) {
    return null;
  }

  switch (expense.status) {
    case 'DRAFT':
      return 'submit';
    case 'SUBMITTED':
      return 'withdraw';
    case 'REJECTED':
      return 'resubmit';
    default:
      return null;
  }
}

/** The approved total may differ from the requested one only for a single-line Expense (no split rule exists). */
export function canChangeApprovedAmount(expense: Expense): boolean {
  return expense.lines.length === 1;
}

export const EXPENSE_STATUS_LABEL: Record<ExpenseStatus, string> = {
  DRAFT: '下書き',
  SUBMITTED: '申請中',
  POSTED: '承認済み',
  REJECTED: '却下',
  CONFIRMED: '旧形式（確認待ち）',
};

export function formatYen(amount: number | null | undefined): string {
  return amount === null || amount === undefined ? '—' : `${amount.toLocaleString('en-US')}円`;
}

/** Workflow-specific wording for the errors the approval endpoints return. */
export function expenseActionErrorMessage(error: unknown): string {
  if (error instanceof ApiError) {
    if (error.code === 'stageart_expense_conflict') {
      return 'この申請は既に処理されたか、内容が変更されています。最新の内容を確認してください。';
    }

    if (error.code === 'stageart_accounting_disabled') {
      return 'この団体では会計機能が開始されていないため、承認できません。';
    }

    if (error.statusCode === 422) {
      return '入力内容を確認してください。';
    }
  }

  return getErrorMessage(error);
}
