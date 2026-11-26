import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { useAuth } from '@/auth/AuthContext';

import {
  createProductionDelegate,
  deleteProductionDelegate,
  fetchMyProductionDelegateRoles,
  fetchProductionDelegates,
  updateProductionDelegate,
} from './api';

export function useProductionDelegates(productionId: string | undefined) {
  const { apiClient, status } = useAuth();

  return useQuery({
    queryKey: ['production-delegates', productionId],
    queryFn: () => fetchProductionDelegates(apiClient, productionId as string),
    enabled: status === 'authenticated' && !!productionId,
  });
}

export function useCreateProductionDelegate(productionId: string | undefined) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (fields: { personId: string; role: string }) => createProductionDelegate(apiClient, productionId as string, fields),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['production-delegates', productionId] }),
  });
}

export function useUpdateProductionDelegate(productionId: string | undefined) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, ...fields }: { id: string; role: string; status: string }) => updateProductionDelegate(apiClient, id, fields),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['production-delegates', productionId] }),
  });
}

export function useDeleteProductionDelegate(productionId: string | undefined) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (delegateId: string) => deleteProductionDelegate(apiClient, delegateId),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['production-delegates', productionId] }),
  });
}

/** R14-B stage 9: the caller's own delegate rows in the Production (GET /productions/{id}/delegates/me). */
export function useMyProductionDelegateRoles(productionId: string | undefined) {
  const { apiClient, status } = useAuth();

  return useQuery({
    queryKey: ['my-delegate-roles', productionId],
    queryFn: () => fetchMyProductionDelegateRoles(apiClient, productionId as string),
    enabled: status === 'authenticated' && !!productionId,
  });
}
