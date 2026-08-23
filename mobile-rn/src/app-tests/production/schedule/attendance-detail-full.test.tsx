import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import {
  attendanceConfirmationRoster,
  currentPerson,
  mockFetchRoutes,
  productionOne,
  rehearsalConfirmed,
} from '@/features/attendance/__fixtures__/attendanceFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/** 正常取得 + 回答済み状態: an already-ATTENDING self record renders its
 * current status, and the roster shows every Person's status (not just
 * the caller's own). */
describe('Attendance detail: full roster, already-responded state', () => {
  it('shows my own current status and the full roster', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne },
      { test: (u) => u.endsWith('/me'), status: 200, body: currentPerson },
      { test: (u) => u.endsWith('/rehearsals/rehearsal-2'), status: 200, body: rehearsalConfirmed },
      {
        test: (u) => u.includes('/rehearsals/rehearsal-2/attendances'),
        status: 200,
        body: attendanceConfirmationRoster,
      },
    ]);

    renderRouter('src/app', { initialUrl: '/production/prod-1/schedule/attendance/rehearsal-2' });

    await waitFor(() => expect(screen.getByTestId('attendance-my-record')).toBeVisible());
    expect(screen.getByTestId('attendance-my-status')).toHaveTextContent('出席');
    expect(screen.getAllByTestId('attendance-roster-row')).toHaveLength(1);
  });
});
