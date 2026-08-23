import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import {
  currentPerson,
  mockFetchRoutes,
  productionOne,
  rehearsalScheduleAdjustment,
  scheduleAdjustmentRoster,
} from '@/features/attendance/__fixtures__/attendanceFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * §21/§23: renders the comment thread (empty state) and the form,
 * submit disabled while the draft is empty. The full type-then-submit
 * interaction hits the same renderRouter()-specific local-useState-press
 * limitation already documented in schedule-comment-post.test.tsx and
 * schedule-range-toggle.test.tsx - the actual POST behavior is verified
 * directly instead, in src/features/schedule/api.test.ts's
 * "posts a Rehearsal Comment body to the correct route" test.
 */
describe('Attendance detail: Rehearsal-level comments form', () => {
  it('renders the empty comment thread and a submit button disabled while the draft is empty', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne },
      { test: (u) => u.endsWith('/me'), status: 200, body: currentPerson },
      { test: (u) => u.endsWith('/rehearsals/rehearsal-1'), status: 200, body: rehearsalScheduleAdjustment },
      { test: (u) => u.includes('/rehearsals/rehearsal-1/attendances'), status: 200, body: scheduleAdjustmentRoster },
      { test: (u) => u.endsWith('/rehearsals/rehearsal-1/schedule-comments'), status: 200, body: [] },
    ]);

    renderRouter('src/app', { initialUrl: '/production/prod-1/schedule/attendance/rehearsal-1' });

    await waitFor(() => expect(screen.getByTestId('comments-empty')).toBeVisible());
    expect(screen.getByTestId('rehearsal-comment-input')).toBeVisible();
    expect(screen.getByTestId('rehearsal-comment-submit').props.accessibilityState?.disabled).toBe(true);
  });
});
