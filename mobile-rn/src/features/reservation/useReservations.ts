import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { useAuth } from '@/auth/AuthContext';

import { cancelReservation, createReservation, fetchReservationsForPerformance, lookupReservation, updateReservation } from './api';

export function useCreateReservation(performanceId: string | undefined) {
  return useMutation({
    mutationFn: (fields: { ticketId: string; bookerName: string; bookerEmail: string; guestCount: number }) =>
      createReservation(performanceId as string, fields),
  });
}

export function useReservationLookup(reservationNumber: string, email: string, enabled: boolean) {
  return useQuery({
    queryKey: ['reservation-lookup', reservationNumber, email],
    queryFn: () => lookupReservation(reservationNumber, email),
    enabled,
    retry: false,
  });
}

export function useUpdateReservation() {
  return useMutation({
    mutationFn: (vars: { reservationNumber: string; email: string; guestCount: number }) =>
      updateReservation(vars.reservationNumber, vars.email, vars.guestCount),
  });
}

export function useCancelReservation() {
  return useMutation({
    mutationFn: (vars: { reservationNumber: string; email: string }) => cancelReservation(vars.reservationNumber, vars.email),
  });
}

export function useReservationsForPerformance(performanceId: string | undefined) {
  const { apiClient, status } = useAuth();

  return useQuery({
    queryKey: ['performance-reservations', performanceId],
    queryFn: () => fetchReservationsForPerformance(apiClient, performanceId as string),
    enabled: status === 'authenticated' && !!performanceId,
  });
}

export function useInvalidateReservationLookup() {
  const queryClient = useQueryClient();

  return (reservationNumber: string, email: string) =>
    queryClient.invalidateQueries({ queryKey: ['reservation-lookup', reservationNumber, email] });
}
