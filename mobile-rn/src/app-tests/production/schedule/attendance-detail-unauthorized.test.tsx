import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, productionOne } from '@/features/attendance/__fixtures__/attendanceFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

describe('Attendance detail: Unauthorized (403)', () => {
  it('shows the Authorization-specific error and a Retry control', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne },
      { test: (u) => u.endsWith('/me'), status: 200, body: { id: 'person-99', word_press_user_id: 99 } },
      {
        test: (u) => u.endsWith('/rehearsals/rehearsal-1'),
        status: 403,
        body: { code: 'stageart_rehearsal_attendance_access_denied', message: 'Forbidden' },
      },
    ]);

    renderRouter('src/app', { initialUrl: '/production/prod-1/schedule/attendance/rehearsal-1' });

    await waitFor(() => expect(screen.getByTestId('attendance-error')).toBeVisible());
    expect(screen.getByText('この情報を表示する権限がありません。')).toBeVisible();
    expect(screen.getByTestId('attendance-retry')).toBeVisible();
  });
});
