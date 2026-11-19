/**
 * 修正8 経費ワークフロー: the Expense as GET /expenses/{id} and
 * GET /productions/{id}/expenses return it (ExpenseResult.php).
 * Amounts are integer yen totals; `requested_amount` is what the
 * applicant asked for, `approved_amount` what was approved and posted.
 */
export type ExpenseStatus = 'DRAFT' | 'SUBMITTED' | 'POSTED' | 'REJECTED' | 'CONFIRMED';

export type ExpenseLine = {
  id: string;
  account_id: string;
  amount: number;
  description: string | null;
};

export type ExpenseReviewAction = 'SUBMITTED' | 'WITHDRAWN' | 'APPROVED' | 'REJECTED' | 'MIGRATED';

export type ExpenseReview = {
  revision: number;
  action: ExpenseReviewAction;
  requested_amount: number | null;
  approved_amount: number | null;
  lines: { account_id: string; amount: number; description: string | null }[];
  reason: string | null;
  actor_person_id: string;
  acted_at: string;
};

export type Expense = {
  id: string;
  production_id: string;
  payer_person_id: string | null;
  status: ExpenseStatus;
  lines: ExpenseLine[];
  total_amount: number;
  created_by: string;
  created_at: string;
  updated_by: string;
  updated_at: string;
  confirmed_by: string | null;
  confirmed_at: string | null;
  revision: number;
  submitted_by: string | null;
  submitted_at: string | null;
  requested_amount: number | null;
  approved_amount: number | null;
  approved_by: string | null;
  approved_at: string | null;
  rejected_by: string | null;
  rejected_at: string | null;
  rejection_reason: string | null;
  posted_journal_entry_id: string | null;
  reviews: ExpenseReview[];
};
