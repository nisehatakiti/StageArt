/**
 * Budget API shapes (plugin BudgetResult / BudgetItemResult). Amounts are
 * whole yen, always positive; revenue vs expense comes from the item's
 * type, never from a sign (Budget.md "Amount").
 */
export type BudgetStatus = 'DRAFT' | 'ACTIVE' | 'ARCHIVED';

export type BudgetLine = {
  id: string;
  account_id: string;
  amount: number;
  notes: string | null;
};

export type Budget = {
  id: string;
  production_id: string;
  name: string;
  status: BudgetStatus;
  lines: BudgetLine[];
  created_by: string;
  created_at: string;
  updated_by: string;
  updated_at: string;
  activated_by: string | null;
  activated_at: string | null;
};

/**
 * One 予算項目 (GET /productions/{id}/budget-items). account_id is null
 * for a preset the Organization has not registered yet - the first save
 * registers it.
 */
export type BudgetItem = {
  key: string;
  label: string;
  hint: string | null;
  type: 'REVENUE' | 'EXPENSE';
  account_id: string | null;
  preset: boolean;
  available: boolean;
};

export type BudgetItemInput = {
  key: string;
  amount: number;
  notes?: string | null;
};
