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

const base = { ...attendanceConfirmationRoster[0], rehearsal_id: 'rehearsal-3' };
const roster = [
  { ...base, id: 'attendance-3', person_id: 'person-1', status: 'ATTENDING', actual_status: null },
  // Answered 欠席; a result 欠席 was already recorded.
  { ...base, id: 'attendance-4', person_id: 'person-2', status: 'NOT_ATTENDING', actual_status: 'ABSENT' },
  { ...base, id: 'attendance-5', person_id: 'person-3', status: 'UNANSWERED', actual_status: null },
];

/**
 * PR #7 R8 (2-2, 2-4) / R9 (§2; docs/20-RehearsalManagementScreen.md §13):
 * once the start time has passed the Backend returns the Rehearsal as
 * ACTIVE (実施中). There is no manual 「稽古を終了する」. The manager
 * chooses each member's result from a pull-down and saves with 「更新」
 * through PUT /rehearsal-attendances/{id}/record-actual-status (that
 * endpoint sends no notification).
 *
 * R9: the member's answer and the manager's result are separate. Every
 * member has a pull-down - whether they answered 出席, 欠席 or nothing -
 * the pull-down starts from the recorded result (`actual_status`), and
 * the badge keeps showing the answer. (Up to R8 only 出席 answers could
 * have a result; this test asserted that restriction.)
 */
describe('Attendance detail: ACTIVE Rehearsal - manager records results', () => {
  it('shows 実施中 without a finish button, and saves the chosen results for any answer with 更新', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne },
      { test: (u) => u.endsWith('/me'), status: 200, body: currentPerson },
      {
        test: (u) => u.endsWith('/rehearsal-attendances/attendance-4/record-actual-status'),
        status: 200,
        body: { ...roster[1], actual_status: 'ATTENDED' },
      },
      {
        test: (u) => u.endsWith('/rehearsal-attendances/attendance-5/record-actual-status'),
        status: 200,
        body: { ...roster[2], actual_status: 'EARLY_LEFT' },
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
    expect(screen.getByTestId('attendance-actual-select-attendance-4')).toHaveTextContent(/実績：欠席/);
    expect(screen.getByTestId('attendance-actual-select-attendance-5')).toHaveTextContent(/未記録/);
    expect(screen.queryByTestId('attendance-actual-unrecordable-attendance-4')).toBeNull();
    expect(screen.getByTestId('attendance-actual-save').props.accessibilityState?.disabled).toBe(true);

    // 欠席と回答した人 -> 出席
    fireEvent.press(screen.getByTestId('attendance-actual-select-attendance-4'));
    await waitFor(() => expect(screen.getByTestId('attendance-actual-option-attendance-4-ATTENDED')).toBeVisible());
    fireEvent.press(screen.getByTestId('attendance-actual-option-attendance-4-ATTENDED'));
    await waitFor(() => expect(screen.getByTestId('attendance-actual-select-attendance-4')).toHaveTextContent(/実績：出席/));

    // 未回答の人 -> 早退
    fireEvent.press(screen.getByTestId('attendance-actual-select-attendance-5'));
    await waitFor(() => expect(screen.getByTestId('attendance-actual-option-attendance-5-EARLY_LEFT')).toBeVisible());
    fireEvent.press(screen.getByTestId('attendance-actual-option-attendance-5-EARLY_LEFT'));
    await waitFor(() => expect(screen.getByTestId('attendance-actual-select-attendance-5')).toHaveTextContent(/早退/));

    // Choosing alone does not save.
    const callsFor = (suffix: string) =>
      (global.fetch as jest.Mock).mock.calls.filter(([url]) => String(url).endsWith(suffix));
    expect(callsFor('/rehearsal-attendances/attendance-4/record-actual-status')).toHaveLength(0);

    fireEvent.press(screen.getByTestId('attendance-actual-save'));

    await waitFor(() => expect(callsFor('/rehearsal-attendances/attendance-5/record-actual-status')).toHaveLength(1));
    const [, init4] = callsFor('/rehearsal-attendances/attendance-4/record-actual-status')[0];
    expect(init4?.method).toBe('PUT');
    expect(JSON.parse(String(init4?.body))).toEqual({ status: 'ATTENDED' });
    const [, init5] = callsFor('/rehearsal-attendances/attendance-5/record-actual-status')[0];
    expect(JSON.parse(String(init5?.body))).toEqual({ status: 'EARLY_LEFT' });
    expect(callsFor('/rehearsal-attendances/attendance-3/record-actual-status')).toHaveLength(0);

    const urls = (global.fetch as jest.Mock).mock.calls.map(([url]) => String(url));
    expect(urls.some((url) => /\/rehearsals\/[^/]+\/(activate|complete)$/.test(url))).toBe(false);
  });
});
