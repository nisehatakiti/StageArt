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

/**
 * PR #7 R8 (2-2): there is no manual 「稽古を開始する」/「稽古を終了する」.
 * The Backend moves a CONFIRMED Rehearsal to ACTIVE at its start time and
 * to COMPLETED at its end time whenever the Rehearsal is read or written
 * (RehearsalStatusTimeSync, covered by RehearsalStatusTimeSyncTest). Before
 * the start time the manager sees 確定 with edit/cancel only, and the
 * screen never calls the activate/complete endpoints.
 */
describe('Attendance detail: CONFIRMED Rehearsal has no manual start/finish', () => {
  it('shows edit and cancel, but no start/finish buttons and no result recording', async () => {
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

    await waitFor(() => expect(screen.getByTestId('rehearsal-cancel')).toBeVisible());
    expect(screen.getByTestId('rehearsal-info-status')).toHaveTextContent('確定');
    expect(screen.getByTestId('rehearsal-edit')).toBeVisible();
    expect(screen.queryByTestId('rehearsal-confirm')).toBeNull();
    expect(screen.queryByTestId('rehearsal-activate')).toBeNull();
    expect(screen.queryByTestId('rehearsal-complete')).toBeNull();
    // The actual result is recorded only once the Rehearsal is ACTIVE/COMPLETED.
    expect(screen.queryByTestId('attendance-actual-save')).toBeNull();
    expect(screen.queryByTestId('attendance-actual-select-attendance-3')).toBeNull();

    const urls = (global.fetch as jest.Mock).mock.calls.map(([url]) => String(url));
    expect(urls.some((url) => /\/rehearsals\/[^/]+\/(activate|complete)$/.test(url))).toBe(false);
  });
});
