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

describe('Attendance detail: 備考 (remarks)', () => {
  it('pre-fills an existing remark and sends an edited remark on the next respond', async () => {
    let respondBody: unknown = null;
    const myRecordWithRemarks = { ...scheduleAdjustmentRoster[0], remarks: '既存の備考' };

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
        const updated = { ...myRecordWithRemarks, status: 'AVAILABLE', remarks: '更新後の備考' };
        return { ok: true, status: 200, text: async () => JSON.stringify(updated), json: async () => updated } as Response;
      }
      if (url.includes('/rehearsals/rehearsal-1/attendances')) {
        const rows =
          respondBody !== null
            ? [{ ...myRecordWithRemarks, status: 'AVAILABLE', remarks: '更新後の備考' }, scheduleAdjustmentRoster[1]]
            : [myRecordWithRemarks, scheduleAdjustmentRoster[1]];
        return { ok: true, status: 200, text: async () => JSON.stringify(rows), json: async () => rows } as Response;
      }

      throw new Error(`Unmocked fetch: ${url}`);
    });

    renderRouter('src/app', { initialUrl: '/production/prod-1/schedule/attendance/rehearsal-1' });

    await waitFor(() => expect(screen.getByTestId('attendance-remarks-input')).toHaveDisplayValue('既存の備考'));

    fireEvent.changeText(screen.getByTestId('attendance-remarks-input'), '更新後の備考');
    await waitFor(() => expect(screen.getByTestId('attendance-remarks-input')).toHaveDisplayValue('更新後の備考'));

    fireEvent.press(screen.getByTestId('attendance-respond-AVAILABLE'));

    await waitFor(() => expect(respondBody).toEqual({ status: 'AVAILABLE', remarks: '更新後の備考' }));
    await waitFor(() => expect(screen.getByTestId('attendance-remarks-input')).toHaveDisplayValue('更新後の備考'));
  });
});
