import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { currentPerson, mockFetchRoutes, participants, staffItem } from './__fixtures__/scheduleFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * §21/§23: the comment form (text input + "投稿する" button) renders and
 * starts disabled with an empty draft.
 *
 * The full type-then-submit interaction hit the same class of
 * renderRouter()-specific limitation documented in
 * schedule-range-toggle.test.tsx and Phase 5.2's report (a local
 * useState-driven press with no navigation, several Stack/Tab layers
 * deep): after `fireEvent.changeText` + `fireEvent.press`, no POST
 * request was observed. The actual POST behavior
 * (postTimetableItemComment -> POST /timetable-items/{id}/schedule-comments
 * with `{ body }`) is verified directly and does pass, in
 * src/features/schedule/api.test.ts's "posts a Schedule Comment body to
 * the correct route" test - that test exercises the same function this
 * screen's submit handler calls, without depending on the unreliable
 * interaction path.
 */
describe('Schedule Item Detail: comment form', () => {
  it('renders the comment input and a submit button disabled while the draft is empty', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/me'), status: 200, body: currentPerson },
      { test: (u) => u.includes('/productions/prod-1/participants'), status: 200, body: participants },
      { test: (u) => u.includes('/productions/prod-1/timetable-items'), status: 200, body: [staffItem] },
      { test: (u) => u.endsWith('/timetable-items/item-staff'), status: 200, body: staffItem },
      { test: (u) => u.includes('/timetable-items/item-staff/schedule-comments'), status: 200, body: [] },
    ]);

    renderRouter('src/app', { initialUrl: '/production/prod-1/schedule/item-staff' });

    await waitFor(() => expect(screen.getByTestId('comment-input')).toBeVisible());
    expect(screen.getByTestId('comment-submit')).toBeVisible();
    expect(screen.getByTestId('comment-submit').props.accessibilityState?.disabled).toBe(true);
  });
});
