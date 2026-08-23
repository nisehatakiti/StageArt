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

describe('Attendance detail: Retry after a failed load', () => {
  it('refetches and shows the roster after tapping 再読み込み', async () => {
    let rehearsalCallCount = 0;

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
        rehearsalCallCount += 1;

        if (rehearsalCallCount === 1) {
          const body = { code: 'stageart_rehearsal_not_found', message: 'Not Found' };
          return { ok: false, status: 404, text: async () => JSON.stringify(body), json: async () => body } as Response;
        }

        return {
          ok: true,
          status: 200,
          text: async () => JSON.stringify(rehearsalScheduleAdjustment),
          json: async () => rehearsalScheduleAdjustment,
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

    await waitFor(() => expect(screen.getByTestId('attendance-error')).toBeVisible());

    fireEvent.press(screen.getByTestId('attendance-retry'));

    await waitFor(() => expect(screen.getByTestId('attendance-my-record')).toBeVisible());
    expect(rehearsalCallCount).toBe(2);
  });
});
