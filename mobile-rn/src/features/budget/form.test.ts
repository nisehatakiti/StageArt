import { buildFormRows, computeBudgetTotals, parseAmountText, toBudgetItemsPayload, validateBudgetForm } from './form';
import type { Budget, BudgetItem } from './types';

const items: BudgetItem[] = [
  { key: 'ticket_revenue', label: 'チケット収入', hint: null, type: 'REVENUE', account_id: 'acc-ticket', preset: true, available: true },
  { key: 'venue', label: '会場費', hint: '劇場', type: 'EXPENSE', account_id: 'acc-venue', preset: true, available: true },
  { key: 'travel', label: '旅費交通費', hint: null, type: 'EXPENSE', account_id: null, preset: true, available: true },
  { key: 'communication', label: '通信費', hint: null, type: 'EXPENSE', account_id: 'acc-comm', preset: true, available: false },
  { key: 'account:acc-legacy', label: '車両交通費', hint: null, type: 'EXPENSE', account_id: 'acc-legacy', preset: false, available: false },
];

const budget: Budget = {
  id: 'bud-1',
  production_id: 'prod-1',
  name: 'Version1',
  status: 'DRAFT',
  lines: [
    { id: 'l1', account_id: 'acc-ticket', amount: 450000, notes: null },
    { id: 'l2', account_id: 'acc-venue', amount: 200000, notes: '見積書より' },
    { id: 'l3', account_id: 'acc-legacy', amount: 8000, notes: null },
  ],
  created_by: 'p1',
  created_at: '2026-10-27T12:00:00+09:00',
  updated_by: 'p1',
  updated_at: '2026-10-27T12:00:00+09:00',
  activated_by: null,
  activated_at: null,
};

describe('budget form', () => {
  it('fills saved amounts and keeps unavailable items only when they carry an amount', () => {
    const { rows, unmatchedLines } = buildFormRows(items, budget);

    expect(rows.map((row) => [row.key, row.amountText, row.editable])).toEqual([
      ['ticket_revenue', '450000', true],
      ['venue', '200000', true],
      ['travel', '', true],
      ['account:acc-legacy', '8000', false],
    ]);
    expect(rows[1].notes).toBe('見積書より');
    expect(unmatchedLines).toBe(0);
  });

  it('counts lines that no item covers', () => {
    const { unmatchedLines } = buildFormRows(items, { ...budget, lines: [...budget.lines, { id: 'l4', account_id: 'acc-cash', amount: 1, notes: null }] });

    expect(unmatchedLines).toBe(1);
  });

  it('parses amounts leniently but only whole yen', () => {
    expect(parseAmountText('')).toBe(0);
    expect(parseAmountText('200,000')).toBe(200000);
    expect(parseAmountText('３０００')).toBe(3000);
    expect(parseAmountText('-1')).toBeNull();
    expect(parseAmountText('1.5')).toBeNull();
    expect(parseAmountText('abc')).toBeNull();
    expect(parseAmountText('1000000000000')).toBeNull();
  });

  it('validates the name, each amount and that something is planned', () => {
    const { rows } = buildFormRows(items);

    expect(validateBudgetForm('', rows)).toEqual(['予算名を入力してください。', '少なくとも1つの項目に金額を入力してください。']);
    expect(validateBudgetForm('A案', [{ ...rows[1], amountText: '1.5' }])).toEqual(['金額は0以上の整数（円）で入力してください：会場費']);
    expect(validateBudgetForm('A案', [{ ...rows[1], amountText: '1000' }])).toEqual([]);
  });

  it('totals revenue, expense and the planned balance', () => {
    const { rows } = buildFormRows(items, budget);

    expect(computeBudgetTotals(rows)).toEqual({ revenue: 450000, expense: 208000, balance: 242000 });
  });

  it('sends only items with an amount, with their notes', () => {
    const { rows } = buildFormRows(items, budget);
    rows[0].amountText = '0';
    rows[2].amountText = '12,000';

    expect(toBudgetItemsPayload(rows)).toEqual([
      { key: 'venue', amount: 200000, notes: '見積書より' },
      { key: 'travel', amount: 12000, notes: null },
      { key: 'account:acc-legacy', amount: 8000, notes: null },
    ]);
  });
});
