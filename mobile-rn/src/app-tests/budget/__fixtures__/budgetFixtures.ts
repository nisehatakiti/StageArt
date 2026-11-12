import type { Budget, BudgetItem } from '@/features/budget/types';

import { accountingNotSet, productionOne } from '../../production/__fixtures__/productionShellFixtures';

export const budgetItemsFixture: BudgetItem[] = [
  { key: 'ticket_revenue', label: 'チケット収入', hint: 'チケットの販売で見込める収入', type: 'REVENUE', account_id: null, preset: true, available: true },
  { key: 'other_revenue', label: 'その他の収入', hint: '助成金・補助金、協賛・寄付など', type: 'REVENUE', account_id: null, preset: true, available: true },
  { key: 'venue', label: '会場費', hint: '劇場・ホール・稽古場の使用料', type: 'EXPENSE', account_id: null, preset: true, available: true },
  { key: 'travel', label: '旅費交通費', hint: '公共交通機関・レンタカー・駐車場など', type: 'EXPENSE', account_id: null, preset: true, available: true },
];

type Call = { method: string; url: string; body: unknown };

/**
 * A small in-memory stand-in for the Budget API: POST / PUT store the
 * Budget (registering a fake Account per item, as the server registers
 * the presets), GET returns what was stored - so a test can save, then
 * render the screen again and see the persisted values.
 */
export function mockBudgetServer(options: { budgets?: Budget[]; budgetsStatus?: number } = {}) {
  const budgets = new Map((options.budgets ?? []).map((budget) => [budget.id, budget]));
  const accountByKey = new Map<string, string>();
  const calls: Call[] = [];
  let sequence = 0;

  for (const budget of options.budgets ?? []) {
    for (const line of budget.lines) {
      const item = budgetItemsFixture.find((candidate) => `acc-${candidate.key}` === line.account_id);
      if (item) {
        accountByKey.set(item.key, line.account_id);
      }
    }
  }

  const items = () => budgetItemsFixture.map((item) => ({ ...item, account_id: accountByKey.get(item.key) ?? null }));

  const store = (id: string, name: string, rawItems: { key: string; amount: number; notes?: string | null }[], previous?: Budget): Budget => {
    sequence += 1;
    const budget: Budget = {
      id,
      production_id: 'prod-1',
      name,
      status: 'DRAFT',
      lines: rawItems.map((item, index) => {
        accountByKey.set(item.key, `acc-${item.key}`);
        return { id: `line-${sequence}-${index}`, account_id: `acc-${item.key}`, amount: item.amount, notes: item.notes ?? null };
      }),
      created_by: 'person-1',
      created_at: previous?.created_at ?? '2026-10-27T12:00:00+09:00',
      updated_by: 'person-1',
      updated_at: `2026-10-27T12:${String(sequence).padStart(2, '0')}:00+09:00`,
      activated_by: null,
      activated_at: null,
    };
    budgets.set(id, budget);
    return budget;
  };

  const respond = (status: number, body: unknown) =>
    ({ ok: status >= 200 && status < 300, status, text: async () => JSON.stringify(body), json: async () => body }) as Response;

  global.fetch = jest.fn(async (input: unknown, init?: RequestInit) => {
    const url = String(input);
    const method = (init?.method ?? 'GET').toUpperCase();
    const body = typeof init?.body === 'string' ? JSON.parse(init.body) : undefined;
    calls.push({ method, url, body });
    const path = url.replace(/\?.*$/, '');

    if (path.endsWith('/auth/refresh')) {
      return respond(200, { access_token: 'mock-refreshed-access-token', token_type: 'Bearer', expires_in: 3600 });
    }
    if (path.endsWith('/me')) {
      return respond(200, { id: 'person-1', word_press_user_id: 1, email_verified: true, family_name: '舞台', given_name: '芸術' });
    }
    if (path.endsWith('/organizations')) {
      return respond(200, []);
    }
    if (path.endsWith('/productions/prod-1')) {
      return respond(200, productionOne);
    }
    if (path.endsWith('/productions/prod-1/accounting')) {
      return respond(200, accountingNotSet);
    }
    if (path.endsWith('/productions/prod-1/budget-items')) {
      return respond(200, items());
    }
    if (path.endsWith('/productions/prod-1/budgets') && method === 'GET') {
      return options.budgetsStatus ? respond(options.budgetsStatus, { code: 'stageart_budget_access_denied' }) : respond(200, [...budgets.values()]);
    }
    if (path.endsWith('/productions/prod-1/budgets') && method === 'POST') {
      return respond(201, store(`bud-${budgets.size + 1}`, body.name, body.items));
    }
    const budgetMatch = path.match(/\/budgets\/([^/]+)$/);
    if (budgetMatch && budgets.has(budgetMatch[1])) {
      if (method === 'PUT') {
        return respond(200, store(budgetMatch[1], body.name, body.items, budgets.get(budgetMatch[1])));
      }
      return respond(200, budgets.get(budgetMatch[1]));
    }
    throw new Error(`Unmocked fetch: ${method} ${url}`);
  });

  return { calls, budgets };
}

export const savedBudget: Budget = {
  id: 'bud-9',
  production_id: 'prod-1',
  name: '河童ホームラン2026予算 Version1',
  status: 'DRAFT',
  lines: [
    { id: 'line-a', account_id: 'acc-ticket_revenue', amount: 450000, notes: null },
    { id: 'line-b', account_id: 'acc-venue', amount: 200000, notes: '劇場見積書より' },
  ],
  created_by: 'person-1',
  created_at: '2026-10-27T11:00:00+09:00',
  updated_by: 'person-1',
  updated_at: '2026-10-27T11:00:00+09:00',
  activated_by: null,
  activated_at: null,
};
