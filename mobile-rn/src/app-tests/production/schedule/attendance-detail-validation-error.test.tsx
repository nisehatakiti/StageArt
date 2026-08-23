import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

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

describe('Attendance detail: Validation Error on respond (422)', () => {
  it('shows the Backend-returned message under the response controls without crashing', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne },
      { test: (u) => u.endsWith('/me'), status: 200, body: currentPerson },
      { test: (u) => u.endsWith('/rehearsals/rehearsal-1'), status: 200, body: rehearsalScheduleAdjustment },
      { test: (u) => u.includes('/rehearsals/rehearsal-1/attendances'), status: 200, body: scheduleAdjustmentRoster },
      {
        test: (u) => u.includes('/rehearsal-attendances/attendance-1/respond'),
        status: 422,
        body: { code: 'stageart_rehearsal_attendance_invalid', message: 'Actual status must be ATTENDED, LATE, or ABSENT.' },
      },
    ]);

    renderRouter('src/app', { initialUrl: '/production/prod-1/schedule/attendance/rehearsal-1' });

    await waitFor(() => expect(screen.getByTestId('attendance-respond-AVAILABLE')).toBeVisible());

    fireEvent.press(screen.getByTestId('attendance-respond-AVAILABLE'));

    await waitFor(() => expect(screen.getByTestId('attendance-respond-error')).toBeVisible());
    expect(screen.getByText('読み込みに失敗しました。(422)')).toBeVisible();
  });
});
