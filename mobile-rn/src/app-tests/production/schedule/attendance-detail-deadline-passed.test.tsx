import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { currentPerson, productionOne, rehearsalScheduleAdjustment, scheduleAdjustmentRoster } from '@/features/attendance/__fixtures__/attendanceFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * StageArt Phase 7 (Rehearsal仕様整合) §4/§10: past the response deadline
 * a member can no longer answer/change their own SCHEDULE_ADJUSTMENT
 * response - this is a UX mirror of the Backend guard
 * (RespondRehearsalAttendanceUseCase), not the source of truth for it.
 */
describe('Attendance detail: 回答期限超過', () => {
  it('disables the respond buttons and remarks input, and shows an explanation, once the deadline has passed', async () => {
    const rehearsalWithPastDeadline = { ...rehearsalScheduleAdjustment, response_deadline: '2020-01-01T00:00:00+09:00' };

    global.fetch = jest.fn(async (input: unknown) => {
      const url = String(input);

      if (url.endsWith('/auth/refresh')) {
        return {
          ok: true,
          status: 200,
          text: async () => JSON.stringify({ access_token: 'refreshed-token', token_type: 'Bearer', expires_in: 3600 }),
        } as Response;
      }
      if (url.endsWith('/productions/prod-1')) {
        return { ok: true, status: 200, text: async () => JSON.stringify(productionOne), json: async () => productionOne } as Response;
      }
      if (url.endsWith('/me')) {
        return { ok: true, status: 200, text: async () => JSON.stringify(currentPerson), json: async () => currentPerson } as Response;
      }
      if (url.endsWith('/rehearsals/rehearsal-1')) {
        return {
          ok: true,
          status: 200,
          text: async () => JSON.stringify(rehearsalWithPastDeadline),
          json: async () => rehearsalWithPastDeadline,
        } as Response;
      }
      if (url.includes('/rehearsals/rehearsal-1/attendances')) {
        return {
          ok: true,
          status: 200,
          text: async () => JSON.stringify(scheduleAdjustmentRoster),
          json: async () => scheduleAdjustmentRoster,
        } as Response;
      }

      throw new Error(`Unmocked fetch: ${url}`);
    });

    renderRouter('src/app', { initialUrl: '/production/prod-1/schedule/attendance/rehearsal-1' });

    await waitFor(() => expect(screen.getByTestId('attendance-response-deadline-passed')).toBeVisible());

    expect(screen.getByTestId('attendance-respond-AVAILABLE').props.accessibilityState?.disabled).toBe(true);
    expect(screen.getByTestId('attendance-respond-UNAVAILABLE').props.accessibilityState?.disabled).toBe(true);
    expect(screen.getByTestId('attendance-remarks-input').props.editable).toBe(false);
  });
});
