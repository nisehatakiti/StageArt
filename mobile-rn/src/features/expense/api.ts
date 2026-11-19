import type { ApiClient } from '@/api/client';

import type { Expense, ExpenseStatus } from './types';

/** GET /productions/{id}/expenses - every Expense for an approver, only the requester's own otherwise. */
export function fetchProductionExpenses(client: ApiClient, productionId: string, status?: ExpenseStatus): Promise<Expense[]> {
  return client.get<Expense[]>(`/productions/${productionId}/expenses`, status ? { status } : undefined);
}

/** GET /expenses/{id} - an approver, or the Expense's own applicant. */
export function fetchExpense(client: ApiClient, expenseId: string): Promise<Expense> {
  return client.get<Expense>(`/expenses/${expenseId}`);
}

/**
 * PATCH /expenses/{id}/approve. `revision` is the submission revision
 * shown to the approver - the server refuses (409) if the applicant has
 * changed the application since. `approved_amount` omitted = requested.
 */
export function approveExpense(client: ApiClient, expenseId: string, revision: number, approvedAmount?: number): Promise<Expense> {
  return client.patch<Expense>(`/expenses/${expenseId}/approve`, {
    revision,
    ...(approvedAmount !== undefined ? { approved_amount: approvedAmount } : {}),
  });
}

/** PATCH /expenses/{id}/reject - a reason is required. */
export function rejectExpense(client: ApiClient, expenseId: string, reason: string): Promise<Expense> {
  return client.patch<Expense>(`/expenses/${expenseId}/reject`, { reason });
}

export type ApplicantExpenseAction = 'submit' | 'withdraw' | 'resubmit';

/** PATCH /expenses/{id}/submit | withdraw | resubmit - the applicant only. */
export function applicantExpenseAction(client: ApiClient, expenseId: string, action: ApplicantExpenseAction): Promise<Expense> {
  return client.patch<Expense>(`/expenses/${expenseId}/${action}`);
}
