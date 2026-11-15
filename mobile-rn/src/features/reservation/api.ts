import type { ApiClient } from '@/api/client';
import { publicGet, publicPost, publicPut } from '@/api/publicClient';
import type { Reservation } from '@/types/api';

/** POST /performances/{id}/reservations - unauthenticated, the general-
 * audience booking flow (§10/§40). No StageArt account is required. */
export function createReservation(
  performanceId: string,
  fields: { ticketId: string; bookerName: string; bookerEmail: string; guestCount: number }
): Promise<Reservation> {
  return publicPost<Reservation>(`/performances/${performanceId}/reservations`, {
    ticket_id: fields.ticketId,
    booker_name: fields.bookerName,
    booker_email: fields.bookerEmail,
    guest_count: fields.guestCount,
  });
}

/** GET /reservations/lookup?number=...&email=... - self-service lookup
 * (§10/§32), gated only by the reservation-number + booking-email pair
 * matching (verified server-side), never a StageArt login. */
export function lookupReservation(reservationNumber: string, email: string): Promise<Reservation> {
  const query = new URLSearchParams({ number: reservationNumber, email }).toString();
  return publicGet<Reservation>(`/reservations/lookup?${query}`);
}

/** PUT /reservations/{number} - §16/§29/§42: GuestCount is the only
 * field a self-service caller can change; the server enforces the
 * before-sales-end / after-sales-end-before-start / after-start
 * three-case matrix regardless of what the client believes. */
export function updateReservation(reservationNumber: string, email: string, guestCount: number): Promise<Reservation> {
  return publicPut<Reservation>(`/reservations/${reservationNumber}`, { email, guest_count: guestCount });
}

/** POST /reservations/{number}/cancel - idempotent (§43): cancelling an
 * already-CANCELLED Reservation returns its current state rather than
 * erroring. */
export function cancelReservation(reservationNumber: string, email: string): Promise<Reservation> {
  return publicPost<Reservation>(`/reservations/${reservationNumber}/cancel`, { email });
}

/** GET /performances/{id}/reservations - admin listing, authenticated
 * (PrimaryManager or a RESERVATION_MANAGER Delegate only). */
export function fetchReservationsForPerformance(client: ApiClient, performanceId: string): Promise<Reservation[]> {
  return client.get<Reservation[]>(`/performances/${performanceId}/reservations`);
}
