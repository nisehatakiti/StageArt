import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { useAuth } from '@/auth/AuthContext';

import { applicantExpenseAction, approveExpense, fetchExpense, fetchProductionExpenses, rejectExpense, type ApplicantExpenseAction } from './api';
import type { Expense, ExpenseStatus } from './types';

export function useProductionExpenses(productionId: string | undefined, status: ExpenseStatus | undefined, allowed = true) {
  const { apiClient, status: authStatus } = useAuth();

  return useQuery({
    queryKey: ['production-expenses', productionId, status ?? 'ALL'],
    queryFn: () => fetchProductionExpenses(apiClient, productionId as string, status),
    enabled: authStatus === 'authenticated' && !!productionId && allowed,
  });
}

export function useExpense(expenseId: string | undefined) {
  const { apiClient, status } = useAuth();

  return useQuery({
    queryKey: ['expense', expenseId],
    queryFn: () => fetchExpense(apiClient, expenseId as string),
    enabled: status === 'authenticated' && !!expenseId,
  });
}

/**
 * After any transition the Expense itself, its Production's lists and
 * the Production Accounting Summary (an approval changes Actual) are
 * refetched from the server - nothing is patched locally.
 */
function useInvalidateAfterChange() {
  const queryClient = useQueryClient();

  return (expense: Expense) => {
    queryClient.setQueryData(['expense', expense.id], expense);
    void queryClient.invalidateQueries({ queryKey: ['production-expenses', expense.production_id] });
    void queryClient.invalidateQueries({ queryKey: ['production-accounting', expense.production_id] });
  };
}

export function useApproveExpense(expenseId: string | undefined) {
  const { apiClient } = useAuth();
  const onChanged = useInvalidateAfterChange();

  return useMutation({
    mutationFn: ({ revision, approvedAmount }: { revision: number; approvedAmount?: number }) =>
      approveExpense(apiClient, expenseId as string, revision, approvedAmount),
    onSuccess: onChanged,
  });
}

export function useRejectExpense(expenseId: string | undefined) {
  const { apiClient } = useAuth();
  const onChanged = useInvalidateAfterChange();

  return useMutation({
    mutationFn: (reason: string) => rejectExpense(apiClient, expenseId as string, reason),
    onSuccess: onChanged,
  });
}

export function useApplicantExpenseAction(expenseId: string | undefined) {
  const { apiClient } = useAuth();
  const onChanged = useInvalidateAfterChange();

  return useMutation({
    mutationFn: (action: ApplicantExpenseAction) => applicantExpenseAction(apiClient, expenseId as string, action),
    onSuccess: onChanged,
  });
}
