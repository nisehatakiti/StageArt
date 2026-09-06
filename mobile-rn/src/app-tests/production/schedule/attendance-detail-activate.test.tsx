import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

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
 * StageArt Rehearsal機能完成度向上: a CONFIRMED Rehearsal (per
 * Rehearsal.md's own Status Lifecycle) may become ACTIVE via
 * ActivateRehearsalUseCase - previously wired end-to-end on the Backend
 * (REST route + PHPUnit coverage) but never connected to any Mobile UI.
 * This confirms the management panel now shows "稽古を開始する" instead of
 * "稽古情報を確定する" once CONFIRMED, and that pressing it actually calls
 * POST /rehearsals/{id}/activate.
 */
describe('Attendance detail: CONFIRMED Rehearsal management actions', () => {
  it('shows the activate action (not confirm) and calls POST /rehearsals/{id}/activate', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne },
      { test: (u) => u.endsWith('/me'), status: 200, body: currentPerson },
      { test: (u) => u.endsWith('/rehearsals/rehearsal-2/activate'), status: 200, body: { ...rehearsalConfirmed, status: 'ACTIVE' } },
      { test: (u) => u.endsWith('/rehearsals/rehearsal-2'), status: 200, body: rehearsalConfirmed },
      {
        test: (u) => u.includes('/rehearsals/rehearsal-2/attendances'),
        status: 200,
        body: attendanceConfirmationRoster,
      },
    ]);

    renderRouter('src/app', { initialUrl: '/production/prod-1/schedule/attendance/rehearsal-2' });

    await waitFor(() => expect(screen.getByTestId('rehearsal-activate')).toBeVisible());
    expect(screen.queryByTestId('rehearsal-confirm')).toBeNull();
    expect(screen.getByTestId('rehearsal-cancel')).toBeVisible();

    fireEvent.press(screen.getByTestId('rehearsal-activate'));

    await waitFor(() => {
      const activateCall = (global.fetch as jest.Mock).mock.calls.find(([url]) => String(url).endsWith('/rehearsals/rehearsal-2/activate'));
      expect(activateCall).toBeDefined();
    });
  });
});
