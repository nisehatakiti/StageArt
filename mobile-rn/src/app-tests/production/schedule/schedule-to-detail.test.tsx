import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { castItem, comments, currentPerson, mockFetchRoutes, participants, staffItem } from './__fixtures__/scheduleFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * §39 Navigation Tests: 予定 -> Schedule Item Detail -> Back, mounted
 * directly at the Production Shell's schedule tab (matches
 * production-shell.test.tsx's precedent of deep-linking directly to a
 * nested tab route rather than re-walking Login/Home/Production every
 * test). Every press here triggers real Expo Router navigation
 * (push/back), the interaction shape already proven reliable in Phase
 * 5.2's home-to-production test - unlike a local-state-only toggle with
 * no navigation, which Phase 5.2 found unreliable under renderRouter()
 * (see schedule-range-toggle.test.tsx for how that is handled instead).
 */
describe('Schedule: list -> Item Detail -> back', () => {
  it('shows Production Schedule Items grouped by day, with Shared Visibility and Personal Highlight, then navigates into and out of Item Detail', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/me'), status: 200, body: currentPerson },
      { test: (u) => u.includes('/productions/prod-1/participants'), status: 200, body: participants },
      { test: (u) => u.includes('/productions/prod-1/timetable-items'), status: 200, body: [staffItem, castItem] },
      { test: (u) => u.endsWith('/timetable-items/item-staff'), status: 200, body: staffItem },
      { test: (u) => u.includes('/timetable-items/item-staff/schedule-comments'), status: 200, body: comments },
    ]);

    renderRouter('src/app', { initialUrl: '/production/prod-1/schedule' });

    // Shared Visibility: both a STAFF-Role Item and a Person-targeted
    // Item (targeting a different Production Member) are visible
    // together - no Role-based filtering hides either.
    await waitFor(() => expect(screen.getByTestId('schedule-item-item-staff')).toBeVisible());
    expect(screen.getByTestId('schedule-item-item-cast')).toBeVisible();

    // Personal Highlight: I am person-1 with Participant Type CAST.
    // item-cast targets person-1 directly -> highlighted. item-staff
    // has Participant Type LIGHTING (not mine) and no target Person ->
    // not highlighted, but still fully visible (Highlight != Filter).
    expect(screen.getByTestId('highlight-badge-item-cast')).toBeVisible();
    expect(screen.queryByTestId('highlight-badge-item-staff')).toBeNull();

    // Stage Usage: item-staff's Venue is exactly 舞台.
    expect(screen.getByTestId('stage-usage-badge-item-staff')).toBeVisible();

    // Published-only badge always shown.
    expect(screen.getByTestId('published-badge')).toBeVisible();

    await fireEvent.press(screen.getByTestId('schedule-item-item-staff'));

    await waitFor(() => expect(screen.getByTestId('detail-category')).toBeVisible());
    expect(screen.getByTestId('detail-category')).toHaveTextContent('シュート');
    expect(screen.getByTestId('detail-venue')).toHaveTextContent('舞台（舞台使用）');
    expect(screen.getByTestId('detail-participant-type')).toHaveTextContent('LIGHTING');

    await waitFor(() => expect(screen.getByTestId('comment-comment-1')).toBeVisible());
    // Phase 6.5 adds Edit/Delete controls to every comment row (§08 of
    // Phase 6.4's report: shown unconditionally, not hidden by author),
    // so the row's full text content now also includes "編集"/"削除" -
    // matched with a substring regex instead of full equality.
    expect(screen.getByTestId('comment-comment-1')).toHaveTextContent(/少し遅れますperson-2 ・ 2026\/08\/18 09:00/);

    await fireEvent.press(screen.getByTestId('schedule-detail-back'));

    await waitFor(() => expect(screen.getByTestId('schedule-day-list')).toBeVisible());
    expect(screen.queryByTestId('detail-category')).toBeNull();
  });
});
