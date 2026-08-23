import { useQuery } from '@tanstack/react-query';

import { useAuth } from '@/auth/AuthContext';

import { fetchParticipants, fetchProductionTimetableItems, fetchTimetableItem } from './api';
import type { ScheduleRange } from './dateRange';

/**
 * §29 Query Architecture: `range` (from/to strings, or undefined for
 * Full Range) is part of the Query Key so the default "当日+翌日" view
 * and "全期間" view are cached as distinct entries and never overwrite
 * each other (§29: "当日＋翌日とFull Rangeを同じQuery Cacheに無理に
 * 押し込まない").
 */
export function useProductionSchedule(productionId: string | undefined, range: ScheduleRange) {
  const { apiClient, status } = useAuth();

  return useQuery({
    queryKey: ['production-timetable', productionId, range?.from ?? null, range?.to ?? null],
    queryFn: () => fetchProductionTimetableItems(apiClient, productionId as string, range),
    enabled: status === 'authenticated' && !!productionId,
  });
}

export function useTimetableItem(itemId: string | undefined) {
  const { apiClient, status } = useAuth();

  return useQuery({
    queryKey: ['timetable-item', itemId],
    queryFn: () => fetchTimetableItem(apiClient, itemId as string),
    enabled: status === 'authenticated' && !!itemId,
  });
}

/** Used only to derive the current Person's own Participant Type(s) for
 * Personal Highlight - never to filter the Schedule Item set itself. */
export function useParticipants(productionId: string | undefined) {
  const { apiClient, status } = useAuth();

  return useQuery({
    queryKey: ['participants', productionId],
    queryFn: () => fetchParticipants(apiClient, productionId as string),
    enabled: status === 'authenticated' && !!productionId,
  });
}
