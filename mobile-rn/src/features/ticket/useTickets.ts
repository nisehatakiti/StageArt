import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { useAuth } from '@/auth/AuthContext';
import type { TicketBackCondition } from '@/types/api';

import {
  archiveTicket,
  createTicket,
  fetchPublicTickets,
  fetchTickets,
  updateQuotaAndTicketBackSettings,
  updateTicket,
  updateTicketSalesSettings,
} from './api';

export function useTickets(productionId: string | undefined) {
  const { apiClient, status } = useAuth();

  return useQuery({
    queryKey: ['production-tickets', productionId],
    queryFn: () => fetchTickets(apiClient, productionId as string),
    enabled: status === 'authenticated' && !!productionId,
  });
}

export function usePublicTickets(productionId: string | undefined) {
  return useQuery({
    queryKey: ['public-tickets', productionId],
    queryFn: () => fetchPublicTickets(productionId as string),
    enabled: !!productionId,
  });
}

export function useCreateTicket(productionId: string | undefined) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (fields: { name: string; price: number; remarks?: string }) => createTicket(apiClient, productionId as string, fields),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['production-tickets', productionId] }),
  });
}

export function useUpdateTicket(productionId: string | undefined) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (vars: { ticketId: string; name: string; price: number; remarks: string | null }) =>
      updateTicket(apiClient, vars.ticketId, vars),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['production-tickets', productionId] }),
  });
}

export function useArchiveTicket(productionId: string | undefined) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (ticketId: string) => archiveTicket(apiClient, ticketId),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['production-tickets', productionId] }),
  });
}

export function useUpdateTicketSalesSettings(productionId: string | undefined) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (fields: {
      ticketPublicationAt?: string | null;
      ticketSalesStartAt?: string | null;
      ticketSalesEndRule?: string | null;
      ticketSalesEndParameter?: string | null;
    }) => updateTicketSalesSettings(apiClient, productionId as string, fields),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['public-tickets', productionId] }),
  });
}

export function useUpdateQuotaAndTicketBackSettings(productionId: string | undefined) {
  const { apiClient } = useAuth();

  return useMutation({
    mutationFn: (fields: {
      quotaEnabled: boolean;
      quotaCount: number | null;
      quotaBuybackEnabled: boolean;
      quotaShortfallUnitPrice: number | null;
      ticketBackMode: string | null;
      ticketBackConditions: TicketBackCondition[];
    }) => updateQuotaAndTicketBackSettings(apiClient, productionId as string, fields),
  });
}
