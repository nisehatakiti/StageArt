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

const overdue = {
  ...rehearsalScheduleAdjustment,
  status: 'SCHEDULED',
  start_date_time: '2020-01-01T10:00:00+09:00',
  end_date_time: '2020-01-01T12:00:00+09:00',
};

/**
 * PR #7 R10 (docs/20-RehearsalManagementScreen.md §23.2): a 調整中
 * Rehearsal past its start time stays 調整中; the detail screen tells a
 * manager to confirm or cancel it, and shows a member nothing extra.
 */
describe('Attendance detail: overdue 調整中 Rehearsal', () => {
  it('PrimaryManager: the overdue 調整中 Rehearsal shows the notice next to 確定 and 中止', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne },
      { test: (u) => u.endsWith('/me'), status: 200, body: currentPerson },
      { test: (u) => u.endsWith('/rehearsals/rehearsal-1'), status: 200, body: overdue },
      { test: (u) => u.includes('/rehearsals/rehearsal-1/attendances'), status: 200, body: scheduleAdjustmentRoster },
    ]);

    renderRouter('src/app', { initialUrl: '/production/prod-1/schedule/attendance/rehearsal-1' });

    await waitFor(() => expect(screen.getByTestId('rehearsal-overdue-notice')).toHaveTextContent('開始予定時刻を過ぎています。確定または中止を選択してください。'));
    expect(screen.getByTestId('rehearsal-confirm')).toBeVisible();
    expect(screen.getByTestId('rehearsal-cancel')).toBeVisible();
    expect(screen.getByTestId('rehearsal-info-status')).toHaveTextContent('調整中');
  });
});
