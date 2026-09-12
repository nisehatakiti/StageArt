import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { useAuth } from '@/auth/AuthContext';

import { cancelPerformance, createPerformance, fetchPerformance, fetchPerformances, updatePerformance } from './api';

export function usePerformances(productionId: string | undefined) {
  const { apiClient, status } = useAuth();

  return useQuery({
    queryKey: ['production-performances', productionId],
    queryFn: () => fetchPerformances(apiClient, productionId as string),
    enabled: status === 'authenticated' && !!productionId,
  });
}

export function usePerformance(performanceId: string | undefined) {
  const { apiClient, status } = useAuth();

  return useQuery({
    queryKey: ['performance', performanceId],
    queryFn: () => fetchPerformance(apiClient, performanceId as string),
    enabled: status === 'authenticated' && !!performanceId,
  });
}

export function useCreatePerformance(productionId: string | undefined) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (fields: { performanceDate: string; startTime: string; endTime?: string; capacity?: number; remarks?: string; symbol?: string }) =>
      createPerformance(apiClient, productionId as string, fields),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['production-performances', productionId] }),
  });
}

export function useUpdatePerformance(performanceId: string | undefined) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (fields: {
      performanceDate: string;
      startTime: string;
      endTime: string | null;
      capacity: number;
      remarks: string | null;
      symbol: string | null;
      status?: string;
    }) => updatePerformance(apiClient, performanceId as string, fields),
    onSuccess: (performance) => {
      queryClient.invalidateQueries({ queryKey: ['performance', performanceId] });
      queryClient.invalidateQueries({ queryKey: ['production-performances', performance.production_id] });
    },
  });
}

export function useCancelPerformance(productionId: string | undefined) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (performanceId: string) => cancelPerformance(apiClient, performanceId),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['production-performances', productionId] }),
  });
}
