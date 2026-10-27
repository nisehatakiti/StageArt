import type { ApiClient } from '@/api/client';

import type { Budget, BudgetItem, BudgetItemInput } from './types';

export function fetchProductionBudgets(client: ApiClient, productionId: string): Promise<Budget[]> {
  return client.get<Budget[]>(`/productions/${productionId}/budgets`);
}

export function fetchBudget(client: ApiClient, budgetId: string): Promise<Budget> {
  return client.get<Budget>(`/budgets/${budgetId}`);
}

export function fetchBudgetItems(client: ApiClient, productionId: string): Promise<BudgetItem[]> {
  return client.get<BudgetItem[]>(`/productions/${productionId}/budget-items`);
}

/** Saved by 予算項目 (`items`), never by Account id - see BudgetItemLineFactory. */
export function createBudget(client: ApiClient, productionId: string, name: string, items: BudgetItemInput[]): Promise<Budget> {
  return client.post<Budget>(`/productions/${productionId}/budgets`, { name, items });
}

export function updateBudget(client: ApiClient, budgetId: string, name: string, items: BudgetItemInput[]): Promise<Budget> {
  return client.put<Budget>(`/budgets/${budgetId}`, { name, items });
}
