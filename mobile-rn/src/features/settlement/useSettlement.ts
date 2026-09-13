import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { useAuth } from '@/auth/AuthContext';

import { fetchProductionSettlementSummary, settleProductionMember } from './api';

export function useProductionSettlementSummary(productionId: string | undefined) {
  const { apiClient, status } = useAuth();

  return useQuery({
    queryKey: ['production-settlement', productionId],
    queryFn: () => fetchProductionSettlementSummary(apiClient, productionId as string),
    enabled: status === 'authenticated' && !!productionId,
  });
}

export function useSettleProductionMember(productionId: string | undefined) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (personId: string) => settleProductionMember(apiClient, productionId as string, personId),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['production-settlement', productionId] }),
  });
}
