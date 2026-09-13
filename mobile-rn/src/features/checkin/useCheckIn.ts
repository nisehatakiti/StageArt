import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { useAuth } from '@/auth/AuthContext';

import {
  changeReservationAttribution,
  checkInByNumber,
  checkInReservation,
  createWalkUpReservation,
  markNoShow,
  reverseCheckIn,
  searchReservationsForCheckIn,
} from './api';

export function useSearchReservationsForCheckIn(performanceId: string | undefined, keyword: string) {
  const { apiClient, status } = useAuth();

  return useQuery({
    queryKey: ['checkin-search', performanceId, keyword],
    queryFn: () => searchReservationsForCheckIn(apiClient, performanceId as string, keyword),
    enabled: status === 'authenticated' && !!performanceId,
  });
}

function useInvalidateCheckInSearch(performanceId: string | undefined) {
  const queryClient = useQueryClient();
  return () => queryClient.invalidateQueries({ queryKey: ['checkin-search', performanceId] });
}

export function useCheckInReservation(performanceId: string | undefined) {
  const { apiClient } = useAuth();
  const invalidate = useInvalidateCheckInSearch(performanceId);

  return useMutation({
    mutationFn: (reservationId: string) => checkInReservation(apiClient, performanceId as string, reservationId),
    onSuccess: invalidate,
  });
}

export function useCheckInByNumber(performanceId: string | undefined) {
  const { apiClient } = useAuth();
  const invalidate = useInvalidateCheckInSearch(performanceId);

  return useMutation({
    mutationFn: (reservationNumber: string) => checkInByNumber(apiClient, performanceId as string, reservationNumber),
    onSuccess: invalidate,
  });
}

export function useMarkNoShow(performanceId: string | undefined) {
  const { apiClient } = useAuth();
  const invalidate = useInvalidateCheckInSearch(performanceId);

  return useMutation({
    mutationFn: (reservationId: string) => markNoShow(apiClient, performanceId as string, reservationId),
    onSuccess: invalidate,
  });
}

export function useReverseCheckIn(performanceId: string | undefined) {
  const { apiClient } = useAuth();
  const invalidate = useInvalidateCheckInSearch(performanceId);

  return useMutation({
    mutationFn: (reservationId: string) => reverseCheckIn(apiClient, performanceId as string, reservationId),
    onSuccess: invalidate,
  });
}

export function useCreateWalkUpReservation(performanceId: string | undefined) {
  const { apiClient } = useAuth();
  const invalidate = useInvalidateCheckInSearch(performanceId);

  return useMutation({
    mutationFn: (fields: {
      ticketId: string;
      bookerName: string;
      bookerEmail: string;
      guestCount: number;
      attributedPersonId?: string | null;
      idempotencyKey: string;
    }) => createWalkUpReservation(apiClient, performanceId as string, fields),
    onSuccess: invalidate,
  });
}

export function useChangeReservationAttribution(performanceId: string | undefined) {
  const { apiClient } = useAuth();
  const invalidate = useInvalidateCheckInSearch(performanceId);

  return useMutation({
    mutationFn: (vars: { reservationId: string; attributedPersonId: string | null }) =>
      changeReservationAttribution(apiClient, vars.reservationId, vars.attributedPersonId),
    onSuccess: invalidate,
  });
}
