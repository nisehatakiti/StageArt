import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import {
  attendanceConfirmationRoster,
  currentPerson,
  mockFetchRoutes,
  productionOne,
  rehearsalCompleted,
} from '@/features/attendance/__fixtures__/attendanceFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * StageArt Rehearsal機能完成度向上: UpdateRehearsalUseCase (Rehearsal.php
 * ::updateBasicInfo()) rejects a COMPLETED (or CANCELLED) Rehearsal - the
 * same `isTerminal()` guard the confirm/activate/complete/cancel actions
 * already respect. No management action, including the new「編集」導線,
 * should render once a Rehearsal reaches a terminal status.
 */
describe('Attendance detail: COMPLETED Rehearsal shows no management actions', () => {
  it('hides edit and every status-transition action', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne },
      { test: (u) => u.endsWith('/me'), status: 200, body: currentPerson },
      { test: (u) => u.endsWith('/rehearsals/rehearsal-4'), status: 200, body: rehearsalCompleted },
      {
        test: (u) => u.includes('/rehearsals/rehearsal-4/attendances'),
        status: 200,
        body: attendanceConfirmationRoster,
      },
    ]);

    renderRouter('src/app', { initialUrl: '/production/prod-1/schedule/attendance/rehearsal-4' });

    await waitFor(() => expect(screen.getByTestId('attendance-summary')).toBeVisible());

    expect(screen.queryByTestId('rehearsal-edit')).toBeNull();
    expect(screen.queryByTestId('rehearsal-confirm')).toBeNull();
    expect(screen.queryByTestId('rehearsal-activate')).toBeNull();
    expect(screen.queryByTestId('rehearsal-complete')).toBeNull();
    expect(screen.queryByTestId('rehearsal-cancel')).toBeNull();
  });
});
