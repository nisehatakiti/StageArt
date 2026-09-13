import type { ApiClient } from '@/api/client';
import type { CheckInResultDto, Reservation } from '@/types/api';

/** GET /performances/{id}/checkin/reservations?keyword=... - reception
 * search (CheckIn.md "# Search": Reservation Number / Booker Name),
 * distinct from the public self-service lookup. */
export function searchReservationsForCheckIn(client: ApiClient, performanceId: string, keyword: string): Promise<Reservation[]> {
  return client.get<Reservation[]>(`/performances/${performanceId}/checkin/reservations`, keyword ? { keyword } : undefined);
}

/** POST /performances/{id}/checkin/reservations/{reservationId} */
export function checkInReservation(client: ApiClient, performanceId: string, reservationId: string): Promise<CheckInResultDto> {
  return client.post<CheckInResultDto>(`/performances/${performanceId}/checkin/reservations/${reservationId}`);
}

/** POST /performances/{id}/checkin/by-number - QR (screenshot-compatible)
 * or manually typed Reservation Number entry. */
export function checkInByNumber(client: ApiClient, performanceId: string, reservationNumber: string): Promise<CheckInResultDto> {
  return client.post<CheckInResultDto>(`/performances/${performanceId}/checkin/by-number`, { reservation_number: reservationNumber });
}

/** POST /performances/{id}/checkin/reservations/{reservationId}/no-show */
export function markNoShow(client: ApiClient, performanceId: string, reservationId: string): Promise<void> {
  return client.post(`/performances/${performanceId}/checkin/reservations/${reservationId}/no-show`);
}

/** POST /performances/{id}/checkin/reservations/{reservationId}/reverse -
 * Check-in Reversal (誤受付の取消). */
export function reverseCheckIn(client: ApiClient, performanceId: string, reservationId: string): Promise<void> {
  return client.post(`/performances/${performanceId}/checkin/reservations/${reservationId}/reverse`);
}

/** POST /performances/{id}/checkin/walk-up - 当日券: create + immediately
 * Check-in one Reservation, in a single atomic Application-layer call.
 * `idempotencyKey` (Phase 0-4統合監査 P1-3) must be a fresh identifier
 * per confirmed Frontend action - retrying the SAME confirmed action
 * (double-tap, network retry) with the SAME key reuses the original
 * registration instead of creating a duplicate one. */
export function createWalkUpReservation(
  client: ApiClient,
  performanceId: string,
  fields: {
    ticketId: string;
    bookerName: string;
    bookerEmail: string;
    guestCount: number;
    attributedPersonId?: string | null;
    idempotencyKey: string;
  }
): Promise<CheckInResultDto> {
  return client.post<CheckInResultDto>(`/performances/${performanceId}/checkin/walk-up`, {
    ticket_id: fields.ticketId,
    booker_name: fields.bookerName,
    booker_email: fields.bookerEmail,
    guest_count: fields.guestCount,
    attributed_person_id: fields.attributedPersonId ?? null,
    idempotency_key: fields.idempotencyKey,
  });
}

/** PUT /reservations/{id}/attribution - corrects "誰扱い". */
export function changeReservationAttribution(client: ApiClient, reservationId: string, attributedPersonId: string | null): Promise<Reservation> {
  return client.put<Reservation>(`/reservations/${reservationId}/attribution`, { attributed_person_id: attributedPersonId });
}
