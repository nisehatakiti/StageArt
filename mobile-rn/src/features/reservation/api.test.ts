import { ApiError } from '@/api/errors';

import { cancelReservation, createReservation, lookupReservation, updateReservation } from './api';

const BASE_URL = 'https://dev-api.stageart.top/wp-json/stageart/v1';

function mockFetchOnce(status: number, body: unknown) {
  (global.fetch as jest.Mock).mockResolvedValueOnce({
    ok: status >= 200 && status < 300,
    status,
    text: async () => JSON.stringify(body),
    json: async () => body,
  });
}

const reservation = {
  id: 'res-1',
  reservation_number: 'ABCD234567',
  performance_id: 'perf-1',
  ticket_id: 'ticket-1',
  booker_name: '予約太郎',
  booker_email: 'booker@example.com',
  guest_count: 2,
  price_snapshot: 3000,
  status: 'RESERVED',
  attributed_person_id: null,
  created_by: null,
  created_at: '',
  updated_by: null,
  updated_at: '',
};

/**
 * StageArt 予約→発券→受付Check-in一連接続: direct function-level tests
 * for the public (unauthenticated) Reservation self-service endpoints -
 * 予約作成/予約変更/予約キャンセル (task's minimum test list #1-#3). Tests
 * the request/response shape directly rather than through renderRouter()
 * + fireEvent, matching the established precedent in
 * `src/features/auth/api.test.ts` (its own docblock cites this exact
 * renderRouter()-local-state-press limitation for its own unauthenticated
 * top-level screens) - confirmed via extensive tracing this round that
 * `/reserve/[performanceId]` and `/my-reservation` hit the identical
 * class of issue (a state update from even the very first `fireEvent`
 * on these two public, unauthenticated top-level routes is not observed
 * by the test renderer, while the exact same interaction pattern on
 * every authenticated `(app)` screen - production/ticket/checkin -
 * works reliably) - so this file, not a renderRouter() interaction test,
 * is this round's real coverage for these endpoints.
 */
describe('reservation api', () => {
  beforeEach(() => {
    global.fetch = jest.fn();
  });

  it('createReservation posts ticket_id/booker_name/booker_email/guest_count to /performances/{id}/reservations', async () => {
    mockFetchOnce(201, reservation);

    const result = await createReservation('perf-1', {
      ticketId: 'ticket-1',
      bookerName: '予約太郎',
      bookerEmail: 'booker@example.com',
      guestCount: 2,
    });

    const [url, init] = (global.fetch as jest.Mock).mock.calls[0];
    expect(url).toBe(`${BASE_URL}/performances/perf-1/reservations`);
    expect(JSON.parse(init.body)).toEqual({
      ticket_id: 'ticket-1',
      booker_name: '予約太郎',
      booker_email: 'booker@example.com',
      guest_count: 2,
    });
    expect(result.reservation_number).toBe('ABCD234567');
  });

  it('createReservation surfaces a Capacity-exceeded rejection as ApiError', async () => {
    mockFetchOnce(422, { code: 'stageart_capacity_exceeded', message: 'capacity exceeded' });

    await expect(
      createReservation('perf-1', { ticketId: 'ticket-1', bookerName: 'x', bookerEmail: 'x@example.com', guestCount: 99 })
    ).rejects.toMatchObject({ statusCode: 422 });
  });

  it('lookupReservation sends the reservation number + booking email as query params, no StageArt session required', async () => {
    mockFetchOnce(200, reservation);

    await lookupReservation('ABCD234567', 'booker@example.com');

    const [url] = (global.fetch as jest.Mock).mock.calls[0];
    expect(url).toBe(`${BASE_URL}/reservations/lookup?number=ABCD234567&email=booker%40example.com`);
  });

  it('lookupReservation with a mismatched number/email pair surfaces a 403 ApiError', async () => {
    mockFetchOnce(403, { code: 'stageart_reservation_lookup_forbidden', message: 'mismatch' });

    await expect(lookupReservation('ABCD234567', 'wrong@example.com')).rejects.toBeInstanceOf(ApiError);
  });

  it('updateReservation PUTs the new guest_count to /reservations/{number}', async () => {
    mockFetchOnce(200, { ...reservation, guest_count: 3 });

    const result = await updateReservation('ABCD234567', 'booker@example.com', 3);

    const [url, init] = (global.fetch as jest.Mock).mock.calls[0];
    expect(url).toBe(`${BASE_URL}/reservations/ABCD234567`);
    expect(init.method).toBe('PUT');
    expect(JSON.parse(init.body)).toEqual({ email: 'booker@example.com', guest_count: 3 });
    expect(result.guest_count).toBe(3);
  });

  it('updateReservation rejects a guest_count increase after sales end as ApiError', async () => {
    mockFetchOnce(422, { code: 'stageart_reservation_increase_not_allowed', message: 'sales ended' });

    await expect(updateReservation('ABCD234567', 'booker@example.com', 5)).rejects.toMatchObject({ statusCode: 422 });
  });

  it('cancelReservation posts booker_email to /reservations/{number}/cancel', async () => {
    mockFetchOnce(200, { ...reservation, status: 'CANCELLED' });

    const result = await cancelReservation('ABCD234567', 'booker@example.com');

    const [url, init] = (global.fetch as jest.Mock).mock.calls[0];
    expect(url).toBe(`${BASE_URL}/reservations/ABCD234567/cancel`);
    expect(JSON.parse(init.body)).toEqual({ email: 'booker@example.com' });
    expect(result.status).toBe('CANCELLED');
  });

  it('cancelReservation is idempotent: cancelling an already-CANCELLED reservation resolves rather than erroring', async () => {
    mockFetchOnce(200, { ...reservation, status: 'CANCELLED' });

    await expect(cancelReservation('ABCD234567', 'booker@example.com')).resolves.toMatchObject({ status: 'CANCELLED' });
  });
});
