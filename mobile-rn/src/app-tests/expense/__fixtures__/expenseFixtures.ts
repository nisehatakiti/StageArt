import type { Expense } from '@/features/expense/types';
import type { Production } from '@/types/api';

import { productionOne } from '../../production/__fixtures__/productionShellFixtures';

export { currentPerson, mockFetchRoutes } from '../../production/__fixtures__/productionShellFixtures';

/** The signed-in Person in these tests is person-1 (productionShellFixtures' /me). */
export const accountingManagerProduction: Production = {
  ...productionOne,
  is_primary_manager: false,
  primary_manager_person_id: 'person-owner',
  delegate_role: 'ACCOUNTING_MANAGER',
  delegate_roles: ['ACCOUNTING_MANAGER'],
};

export const ticketManagerProduction: Production = {
  ...accountingManagerProduction,
  delegate_role: 'TICKET_MANAGER',
  delegate_roles: ['TICKET_MANAGER'],
};

export function expenseFixture(overrides: Partial<Expense> = {}): Expense {
  return {
    id: 'exp-1',
    production_id: 'prod-1',
    payer_person_id: null,
    status: 'SUBMITTED',
    lines: [{ id: 'line-1', account_id: 'acc-1', amount: 5000, description: '衣装代' }],
    total_amount: 5000,
    created_by: 'person-applicant',
    created_at: '2026-10-20T10:00:00+00:00',
    updated_by: 'person-applicant',
    updated_at: '2026-10-20T10:00:00+00:00',
    confirmed_by: null,
    confirmed_at: null,
    revision: 1,
    submitted_by: 'person-applicant',
    submitted_at: '2026-10-20T10:00:00+00:00',
    requested_amount: 5000,
    approved_amount: null,
    approved_by: null,
    approved_at: null,
    rejected_by: null,
    rejected_at: null,
    rejection_reason: null,
    posted_journal_entry_id: null,
    reviews: [
      {
        revision: 1,
        action: 'SUBMITTED',
        requested_amount: 5000,
        approved_amount: null,
        lines: [{ account_id: 'acc-1', amount: 5000, description: '衣装代' }],
        reason: null,
        actor_person_id: 'person-applicant',
        acted_at: '2026-10-20T10:00:00+00:00',
      },
    ],
    ...overrides,
  };
}

export function patchCalls(pathSuffix: string): { url: string; body: unknown }[] {
  return (global.fetch as jest.Mock).mock.calls
    .filter(([url, init]: [string, RequestInit?]) => String(url).endsWith(pathSuffix) && init?.method === 'PATCH')
    .map(([url, init]: [string, RequestInit?]) => ({ url: String(url), body: init?.body ? JSON.parse(String(init.body)) : undefined }));
}
