import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { useAuth } from '@/auth/AuthContext';

import { createBudget, fetchBudget, fetchBudgetItems, fetchProductionBudgets, updateBudget } from './api';
import type { Budget, BudgetItemInput } from './types';

export function useProductionBudgets(productionId: string | undefined) {
  const { apiClient, status } = useAuth();

  return useQuery({
    queryKey: ['production-budgets', productionId],
    queryFn: () => fetchProductionBudgets(apiClient, productionId as string),
    enabled: status === 'authenticated' && !!productionId,
  });
}

export function useBudget(budgetId: string | undefined) {
  const { apiClient, status } = useAuth();

  return useQuery({
    queryKey: ['budget', budgetId],
    queryFn: () => fetchBudget(apiClient, budgetId as string),
    enabled: status === 'authenticated' && !!budgetId,
  });
}

export function useBudgetItems(productionId: string | undefined) {
  const { apiClient, status } = useAuth();

  return useQuery({
    queryKey: ['budget-items', productionId],
    queryFn: () => fetchBudgetItems(apiClient, productionId as string),
    enabled: status === 'authenticated' && !!productionId,
  });
}

/**
 * Creates the Budget when budgetId is undefined, otherwise updates it.
 * Afterwards the lists, the items (the first save registers the preset
 * Accounts) and the Production Accounting Summary are refetched from the
 * server.
 */
export function useSaveBudget(productionId: string | undefined, budgetId: string | undefined) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ name, items }: { name: string; items: BudgetItemInput[] }) =>
      budgetId
        ? updateBudget(apiClient, budgetId, name, items)
        : createBudget(apiClient, productionId as string, name, items),
    onSuccess: (budget: Budget) => {
      queryClient.setQueryData(['budget', budget.id], budget);
      void queryClient.invalidateQueries({ queryKey: ['production-budgets', budget.production_id] });
      void queryClient.invalidateQueries({ queryKey: ['budget-items', budget.production_id] });
      void queryClient.invalidateQueries({ queryKey: ['production-accounting', budget.production_id] });
    },
  });
}
