import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import {
  currentPerson,
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

describe('Attendance detail: 回答 (respond)', () => {
  it('sends the respond PUT and shows the updated status after refetch', async () => {
    let respondBody: unknown = null;

    global.fetch = jest.fn(async (input: unknown, init?: RequestInit) => {
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
          text: async () => JSON.stringify(rehearsalScheduleAdjustment),
          json: async () => rehearsalScheduleAdjustment,
        } as Response;
      }
      if (url.includes('/rehearsal-attendances/attendance-1/respond')) {
        respondBody = init?.body ? JSON.parse(String(init.body)) : null;
        const updated = { ...scheduleAdjustmentRoster[0], status: 'AVAILABLE' };
        return { ok: true, status: 200, text: async () => JSON.stringify(updated), json: async () => updated } as Response;
      }
      if (url.includes('/rehearsals/rehearsal-1/attendances')) {
        // Reflects the just-sent respond result on refetch, matching
        // the roster-invalidation-on-success mutation pattern.
        const rows =
          respondBody !== null
            ? [{ ...scheduleAdjustmentRoster[0], status: 'AVAILABLE' }, scheduleAdjustmentRoster[1]]
            : scheduleAdjustmentRoster;
        return { ok: true, status: 200, text: async () => JSON.stringify(rows), json: async () => rows } as Response;
      }

      throw new Error(`Unmocked fetch: ${url}`);
    });

    renderRouter('src/app', { initialUrl: '/production/prod-1/schedule/attendance/rehearsal-1' });

    await waitFor(() => expect(screen.getByTestId('attendance-my-status')).toHaveTextContent('未回答'));

    fireEvent.press(screen.getByTestId('attendance-respond-AVAILABLE'));

    await waitFor(() => expect(respondBody).toEqual({ status: 'AVAILABLE' }));
    await waitFor(() => expect(screen.getByTestId('attendance-my-status')).toHaveTextContent('参加可能'));
  });
});
