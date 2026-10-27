import { useQuery } from '@tanstack/react-query';

import type { ApiClient } from '@/api/client';
import { useAuth } from '@/auth/AuthContext';

/**
 * GET /productions/{id}/budget-actuals - 公演会計の費目別の予算・実績と
 * 取引明細 (OrganizationAccountingAndProductionCostPolicy.md §3). Every
 * figure is computed by the server from the ACTIVE Budget and the POSTED
 * Journal Entries; this client only displays it.
 */
export type AccountingCategoryType = 'REVENUE' | 'EXPENSE';

export type AccountingCategory = {
  account_id: string;
  name: string;
  type: AccountingCategoryType;
  /** null when the Production has no ACTIVE Budget (not set, not ¥0). */
  budget: number | null;
  actual: number;
};

export type AccountingTransaction = {
  journal_entry_id: string;
  journal_date: string;
  description: string;
  account_id: string;
  account_name: string;
  type: AccountingCategoryType;
  income: number | null;
  expense: number | null;
};

export type ProductionAccountingDetail = {
  production_id: string;
  has_budget: boolean;
  categories: AccountingCategory[];
  transactions: AccountingTransaction[];
  currency: string;
};

export function fetchProductionAccountingDetail(client: ApiClient, productionId: string): Promise<ProductionAccountingDetail> {
  return client.get<ProductionAccountingDetail>(`/productions/${productionId}/budget-actuals`);
}

/**
 * Keyed under ['production-accounting', productionId] so anything that
 * invalidates the Summary refreshes this detail as well.
 */
export function useProductionAccountingDetail(productionId: string | undefined, allowed: boolean) {
  const { apiClient, status } = useAuth();

  return useQuery({
    queryKey: ['production-accounting', productionId, 'detail'],
    queryFn: () => fetchProductionAccountingDetail(apiClient, productionId as string),
    enabled: status === 'authenticated' && !!productionId && allowed,
  });
}
