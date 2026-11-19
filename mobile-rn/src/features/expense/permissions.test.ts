import { ApiError } from '@/api/errors';

import { applicantActionFor, canChangeApprovedAmount, canReviewExpense, expenseActionErrorMessage, formatYen, isOwnExpense } from './permissions';
import type { Expense } from './types';

const base: Expense = {
  id: 'exp-1',
  production_id: 'prod-1',
  payer_person_id: null,
  status: 'SUBMITTED',
  lines: [{ id: 'l1', account_id: 'a', amount: 5000, description: null }],
  total_amount: 5000,
  created_by: 'applicant',
  created_at: '',
  updated_by: 'applicant',
  updated_at: '',
  confirmed_by: null,
  confirmed_at: null,
  revision: 1,
  submitted_by: 'applicant',
  submitted_at: '',
  requested_amount: 5000,
  approved_amount: null,
  approved_by: null,
  approved_at: null,
  rejected_by: null,
  rejected_at: null,
  rejection_reason: null,
  posted_journal_entry_id: null,
  reviews: [],
};

const primaryManager = { is_primary_manager: true, delegate_roles: [] };
const accountingManager = { is_primary_manager: false, delegate_roles: ['ACCOUNTING_MANAGER'] };
const ticketManager = { is_primary_manager: false, delegate_roles: ['TICKET_MANAGER'] };

describe('expense permissions (UI side of the 修正8 rules)', () => {
  it('lets the PrimaryManager and an ACCOUNTING_MANAGER review a SUBMITTED Expense of someone else', () => {
    expect(canReviewExpense(primaryManager, base, 'approver')).toBe(true);
    expect(canReviewExpense(accountingManager, base, 'approver')).toBe(true);
  });

  it('never offers review to other roles, an unloaded Production, or outside SUBMITTED', () => {
    expect(canReviewExpense(ticketManager, base, 'approver')).toBe(false);
    expect(canReviewExpense(undefined, base, 'approver')).toBe(false);
    for (const status of ['DRAFT', 'POSTED', 'REJECTED', 'CONFIRMED'] as const) {
      expect(canReviewExpense(primaryManager, { ...base, status }, 'approver')).toBe(false);
    }
  });

  it('treats the creator, the submitter and the payer as the Expense\'s own people', () => {
    expect(isOwnExpense(base, 'applicant')).toBe(true);
    expect(isOwnExpense({ ...base, submitted_by: 'other-submitter' }, 'other-submitter')).toBe(true);
    expect(isOwnExpense({ ...base, payer_person_id: 'payer' }, 'payer')).toBe(true);
    expect(isOwnExpense(base, 'approver')).toBe(false);
    expect(isOwnExpense(base, undefined)).toBe(true);
    expect(canReviewExpense(primaryManager, { ...base, payer_person_id: 'approver' }, 'approver')).toBe(false);
  });

  it('gives the applicant the next transition for each status, and nobody else any', () => {
    expect(applicantActionFor({ ...base, status: 'DRAFT' }, 'applicant')).toBe('submit');
    expect(applicantActionFor(base, 'applicant')).toBe('withdraw');
    expect(applicantActionFor({ ...base, status: 'REJECTED' }, 'applicant')).toBe('resubmit');
    expect(applicantActionFor({ ...base, status: 'POSTED' }, 'applicant')).toBeNull();
    expect(applicantActionFor({ ...base, status: 'CONFIRMED' }, 'applicant')).toBeNull();
    expect(applicantActionFor(base, 'approver')).toBeNull();
  });

  it('allows a changed approved total for a single-line Expense only', () => {
    expect(canChangeApprovedAmount(base)).toBe(true);
    expect(canChangeApprovedAmount({ ...base, lines: [...base.lines, { id: 'l2', account_id: 'a', amount: 1, description: null }] })).toBe(false);
  });

  it('formats yen and maps workflow errors to Japanese', () => {
    expect(formatYen(2500)).toBe('2,500円');
    expect(formatYen(null)).toBe('—');
    expect(expenseActionErrorMessage(new ApiError(409, 'x', 'stageart_expense_conflict'))).toMatch(/既に処理されたか/);
    expect(expenseActionErrorMessage(new ApiError(409, 'x', 'stageart_accounting_disabled'))).toMatch(/会計機能が開始されていない/);
    expect(expenseActionErrorMessage(new ApiError(422, 'The approved amount must be a positive integer.'))).toBe('入力内容を確認してください。');
    expect(expenseActionErrorMessage(new ApiError(403, 'x', 'stageart_expense_access_denied'))).toBe('この情報を表示する権限がありません。');
  });
});
