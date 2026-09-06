import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import {
  attendanceConfirmationRoster,
  currentPerson,
  mockFetchRoutes,
  productionOne,
  rehearsalActive,
} from '@/features/attendance/__fixtures__/attendanceFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * StageArt Rehearsal機能完成度向上: an ACTIVE Rehearsal (per Rehearsal.md's
 * own Status Lifecycle) may become COMPLETED via CompleteRehearsalUseCase -
 * previously wired end-to-end on the Backend (REST route + PHPUnit
 * coverage) but never connected to any Mobile UI. This confirms the
 * management panel shows "実施済みにする" while ACTIVE, that 中止する
 * (cancel) also remains available (Rehearsal.md: ACTIVE -> CANCELLED is a
 * valid transition too), and that pressing complete actually calls
 * POST /rehearsals/{id}/complete.
 */
describe('Attendance detail: ACTIVE Rehearsal management actions', () => {
  it('shows the complete action and calls POST /rehearsals/{id}/complete', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne },
      { test: (u) => u.endsWith('/me'), status: 200, body: currentPerson },
      { test: (u) => u.endsWith('/rehearsals/rehearsal-3/complete'), status: 200, body: { ...rehearsalActive, status: 'COMPLETED' } },
      { test: (u) => u.endsWith('/rehearsals/rehearsal-3'), status: 200, body: rehearsalActive },
      {
        test: (u) => u.includes('/rehearsals/rehearsal-3/attendances'),
        status: 200,
        body: attendanceConfirmationRoster,
      },
    ]);

    renderRouter('src/app', { initialUrl: '/production/prod-1/schedule/attendance/rehearsal-3' });

    await waitFor(() => expect(screen.getByTestId('rehearsal-complete')).toBeVisible());
    expect(screen.queryByTestId('rehearsal-confirm')).toBeNull();
    expect(screen.queryByTestId('rehearsal-activate')).toBeNull();
    expect(screen.getByTestId('rehearsal-cancel')).toBeVisible();

    fireEvent.press(screen.getByTestId('rehearsal-complete'));

    await waitFor(() => {
      const completeCall = (global.fetch as jest.Mock).mock.calls.find(([url]) => String(url).endsWith('/rehearsals/rehearsal-3/complete'));
      expect(completeCall).toBeDefined();
    });
  });
});
