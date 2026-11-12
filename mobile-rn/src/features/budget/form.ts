import type { Budget, BudgetItem, BudgetItemInput } from './types';

/**
 * Pure logic of the Budget input screen: rows per 予算項目, amount
 * parsing, validation, totals and the save payload. Planned totals are
 * shown while typing (BudgetManagementPolicy.md "Budget Summary") and are
 * never sent or stored - the server keeps only the lines.
 */
export type BudgetFormRow = {
  key: string;
  label: string;
  hint: string | null;
  type: 'REVENUE' | 'EXPENSE';
  /** false: shown only to keep an amount already saved; not editable. */
  editable: boolean;
  amountText: string;
  notes: string | null;
};

export const MAX_BUDGET_AMOUNT = 999_999_999_999;

export const BUDGET_STATUS_LABEL: Record<Budget['status'], string> = {
  DRAFT: '下書き',
  ACTIVE: '採用中',
  ARCHIVED: '不採用',
};

/**
 * One row per available item, with the saved amount where the Budget has
 * a line for it. An unavailable item appears only when the Budget already
 * has an amount on it, so that amount is kept on the next save.
 * unmatchedLines counts lines no item covers (which a save would drop).
 */
export function buildFormRows(items: BudgetItem[], budget?: Budget): { rows: BudgetFormRow[]; unmatchedLines: number } {
  const lineByAccount = new Map((budget?.lines ?? []).map((line) => [line.account_id, line]));
  const matched = new Set<string>();
  const rows: BudgetFormRow[] = [];

  for (const item of items) {
    const line = item.account_id ? lineByAccount.get(item.account_id) : undefined;

    if (line) {
      matched.add(line.account_id);
    }

    if (!item.available && !line) {
      continue;
    }

    rows.push({
      key: item.key,
      label: item.label,
      hint: item.hint,
      type: item.type,
      editable: item.available,
      amountText: line ? String(line.amount) : '',
      notes: line?.notes ?? null,
    });
  }

  return { rows, unmatchedLines: (budget?.lines ?? []).filter((line) => !matched.has(line.account_id)).length };
}

/** Full-width digits and thousands separators are accepted; blank is 0. Null when invalid. */
export function parseAmountText(text: string): number | null {
  const normalized = text
    .replace(/[０-９]/g, (digit) => String.fromCharCode(digit.charCodeAt(0) - 0xfee0))
    .replace(/[,，\s]/g, '');

  if (normalized === '') {
    return 0;
  }

  if (!/^\d{1,12}$/.test(normalized)) {
    return null;
  }

  return Number(normalized);
}

export function validateBudgetForm(name: string, rows: BudgetFormRow[]): string[] {
  const errors: string[] = [];

  if (name.trim() === '') {
    errors.push('予算名を入力してください。');
  }

  const invalid = rows.filter((row) => parseAmountText(row.amountText) === null);

  if (invalid.length > 0) {
    errors.push(`金額は0以上の整数（円）で入力してください：${invalid.map((row) => row.label).join('、')}`);
  } else if (!rows.some((row) => (parseAmountText(row.amountText) ?? 0) > 0)) {
    errors.push('少なくとも1つの項目に金額を入力してください。');
  }

  return errors;
}

export function computeBudgetTotals(rows: BudgetFormRow[]): { revenue: number; expense: number; balance: number } {
  let revenue = 0;
  let expense = 0;

  for (const row of rows) {
    const amount = parseAmountText(row.amountText) ?? 0;

    if (row.type === 'REVENUE') {
      revenue += amount;
    } else {
      expense += amount;
    }
  }

  return { revenue, expense, balance: revenue - expense };
}

/** Rows with an amount above 0; a blank or 0 row is simply not planned. */
export function toBudgetItemsPayload(rows: BudgetFormRow[]): BudgetItemInput[] {
  return rows
    .map((row) => ({ key: row.key, amount: parseAmountText(row.amountText) ?? 0, notes: row.notes }))
    .filter((item) => item.amount > 0);
}

export function formatYenAmount(amount: number): string {
  return `${amount < 0 ? '-' : ''}¥${Math.abs(amount).toLocaleString('en-US')}`;
}
