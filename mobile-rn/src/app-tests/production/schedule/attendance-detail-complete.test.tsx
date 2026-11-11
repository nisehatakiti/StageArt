import { QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

import { ApiClient } from '@/api/client';
import { createQueryClient } from '@/api/queryClient';
import {
  attendanceConfirmationRoster,
  currentPerson,
  mockFetchRoutes,
  productionOne,
  rehearsalActive,
} from '@/features/attendance/__fixtures__/attendanceFixtures';

import RehearsalAttendanceScreen from '../../../app/(app)/production/[id]/schedule/attendance/[rehearsalId]/index';

const mockApiClient = new ApiClient(() => 'mock-access-token');

jest.mock('@/auth/AuthContext', () => ({
  useAuth: () => ({ apiClient: mockApiClient, status: 'authenticated' }),
}));

jest.mock('expo-router', () => ({
  useRouter: () => ({ push: jest.fn(), back: jest.fn(), replace: jest.fn() }),
  useLocalSearchParams: () => ({ id: 'prod-1', rehearsalId: 'rehearsal-3' }),
}));

const roster = [
  { ...attendanceConfirmationRoster[0], id: 'attendance-3', rehearsal_id: 'rehearsal-3', person_id: 'person-1', status: 'ATTENDING' },
  { ...attendanceConfirmationRoster[0], id: 'attendance-4', rehearsal_id: 'rehearsal-3', person_id: 'person-2', status: 'NOT_ATTENDING' },
];

/**
 * PR #7 R8 (2-2, 2-4; docs/20-RehearsalManagementScreen.md §13): once the
 * start time has passed the Backend returns the Rehearsal as ACTIVE
 * (実施中). There is no manual 「稽古を終了する」. The manager chooses each
 * member's result from a pull-down and saves with 「更新」 through
 * PUT /rehearsal-attendances/{id}/record-actual-status (that endpoint
 * sends no notification). A member who did not answer 出席 cannot have a
 * result recorded (RehearsalAttendance::recordActualStatus) and is counted
 * as 欠席 by the member summary; the row says so instead of offering a
 * pull-down that the API would reject.
 */
describe('Attendance detail: ACTIVE Rehearsal - manager records results', () => {
  it('shows 実施中 without a finish button, and saves the chosen result with 更新', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne },
      { test: (u) => u.endsWith('/me'), status: 200, body: currentPerson },
      {
        test: (u) => u.endsWith('/rehearsal-attendances/attendance-3/record-actual-status'),
        status: 200,
        body: { ...roster[0], status: 'LATE' },
      },
      { test: (u) => u.endsWith('/rehearsals/rehearsal-3'), status: 200, body: rehearsalActive },
      { test: (u) => u.includes('/rehearsals/rehearsal-3/attendances'), status: 200, body: roster },
    ]);

    // Rendered in isolation: through renderRouter() a choice made in a row
    // several Stack/Tab layers deep never reached the screen's state (the
    // limitation documented in schedule-comment-post.test.tsx).
    render(
      <QueryClientProvider client={createQueryClient()}>
        <RehearsalAttendanceScreen />
      </QueryClientProvider>
    );

    await waitFor(() => expect(screen.getByTestId('attendance-actual-select-attendance-3')).toBeVisible());
    expect(screen.getByTestId('rehearsal-info-status')).toHaveTextContent('実施中');
    expect(screen.queryByTestId('rehearsal-complete')).toBeNull();
    expect(screen.queryByTestId('rehearsal-activate')).toBeNull();
    expect(screen.getByTestId('attendance-actual-select-attendance-3')).toHaveTextContent(/未記録/);
    expect(screen.queryByTestId('attendance-actual-select-attendance-4')).toBeNull();
    expect(screen.getByTestId('attendance-actual-unrecordable-attendance-4')).toBeVisible();
    expect(screen.getByTestId('attendance-actual-save').props.accessibilityState?.disabled).toBe(true);

    fireEvent.press(screen.getByTestId('attendance-actual-select-attendance-3'));
    await waitFor(() => expect(screen.getByTestId('attendance-actual-option-attendance-3-LATE')).toBeVisible());
    fireEvent.press(screen.getByTestId('attendance-actual-option-attendance-3-LATE'));
    await waitFor(() => expect(screen.getByTestId('attendance-actual-select-attendance-3')).toHaveTextContent(/遅刻/));

    // Choosing alone does not save.
    const callsFor = (suffix: string) =>
      (global.fetch as jest.Mock).mock.calls.filter(([url]) => String(url).endsWith(suffix));
    expect(callsFor('/rehearsal-attendances/attendance-3/record-actual-status')).toHaveLength(0);

    fireEvent.press(screen.getByTestId('attendance-actual-save'));

    await waitFor(() => expect(callsFor('/rehearsal-attendances/attendance-3/record-actual-status')).toHaveLength(1));
    const [, init] = callsFor('/rehearsal-attendances/attendance-3/record-actual-status')[0];
    expect(init?.method).toBe('PUT');
    expect(JSON.parse(String(init?.body))).toEqual({ status: 'LATE' });

    const urls = (global.fetch as jest.Mock).mock.calls.map(([url]) => String(url));
    expect(urls.some((url) => /\/rehearsals\/[^/]+\/(activate|complete)$/.test(url))).toBe(false);
  });
});
