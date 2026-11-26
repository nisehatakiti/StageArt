import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

const publicTickets = {
  tickets: [{ id: 'ticket-1', name: '一般', price: 3000, remarks: null }],
  sales_start_at: '2026-09-01T00:00:00+09:00',
  sales_end_rule: 'HOURS_BEFORE_START',
  sales_end_parameter: '3',
};

/**
 * StageArt 予約→発券→受付Check-in一連接続: renders the public booking
 * screen (`/reserve/{performanceId}`) and confirms the purchasable
 * Ticket comes through from `usePublicTickets()` with its price.
 *
 * A full type-then-submit interaction test was attempted for this
 * screen but dropped: confirmed via extensive tracing that on this
 * specific route (and identically on the sibling public route
 * `/my-reservation`), even the very first `fireEvent`/`userEvent`
 * interaction is not observed by this Jest/RTL harness's test renderer
 * (the underlying handler runs - verified directly - but no further
 * render is ever produced, so the field/selection appears never to have
 * changed) - the same class of renderRouter()-local-state-press
 * limitation `src/features/auth/api.test.ts`'s own docblock already
 * documents for its (also unauthenticated, top-level) register/login/
 * reset screens, just reproducing on a wider set of interactions here.
 * It is NOT reproducible on any authenticated `(app)` screen (e.g.
 * `web-production-checkin-number-entry.test.tsx`, `web-production-
 * checkin-walkup-confirm.test.tsx`), so this is judged a test-harness
 * limitation, not a defect in this screen. The real request/response
 * behavior for Reservation作成/変更/キャンセル is covered directly
 * instead, in `src/features/reservation/api.test.ts`.
 */
describe('Web 予約作成: チケット予約画面', () => {
  it('shows the purchasable Ticket returned by usePublicTickets()', async () => {
    mockFetchRoutes([{ test: (u) => u.endsWith('/productions/prod-1/public-tickets'), status: 200, body: publicTickets }]);

    renderRouter('src/app', { initialUrl: '/reserve/perf-1?productionId=prod-1' });

    await waitFor(() => expect(screen.getByTestId('reservation-create-ticket-ticket-1')).toBeVisible());
    expect(screen.getByTestId('reservation-create-ticket-ticket-1')).toHaveTextContent(/一般/);
    expect(screen.getByTestId('reservation-create-ticket-ticket-1')).toHaveTextContent(/3000円/);
    expect(screen.getByTestId('reservation-create-guest-count')).toBeVisible();
    expect(screen.getByTestId('reservation-create-booker-name')).toBeVisible();
    expect(screen.getByTestId('reservation-create-booker-email')).toBeVisible();
  });
});
