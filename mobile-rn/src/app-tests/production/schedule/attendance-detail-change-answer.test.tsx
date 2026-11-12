import { QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

import { ApiClient } from '@/api/client';
import { createQueryClient } from '@/api/queryClient';
import {
  attendanceConfirmationRoster,
  currentPerson,
  productionOne,
  rehearsalActive,
  rehearsalScheduleAdjustment,
  scheduleAdjustmentRoster,
} from '@/features/attendance/__fixtures__/attendanceFixtures';
import type { Rehearsal, RehearsalAttendance } from '@/types/api';

import RehearsalAttendanceScreen from '../../../app/(app)/production/[id]/schedule/attendance/[rehearsalId]/index';

const mockApiClient = new ApiClient(() => 'mock-access-token');
let mockRehearsalId = 'rehearsal-1';

jest.mock('@/auth/AuthContext', () => ({
  useAuth: () => ({ apiClient: mockApiClient, status: 'authenticated' }),
}));

jest.mock('expo-router', () => ({
  useRouter: () => ({ push: jest.fn(), back: jest.fn(), replace: jest.fn() }),
  useLocalSearchParams: () => ({ id: 'prod-1', rehearsalId: mockRehearsalId }),
}));

const json = (body: unknown) =>
  ({ ok: true, status: 200, text: async () => JSON.stringify(body), json: async () => body }) as Response;

/**
 * Serves one Rehearsal and the member's own record; a PUT .../respond
 * stores the new answer so the refetched roster shows it.
 */
function serve(rehearsal: Rehearsal, own: RehearsalAttendance) {
  let current = own;
  const responds: unknown[] = [];

  global.fetch = jest.fn(async (input: unknown, init?: RequestInit) => {
    const url = String(input);
    if (url.endsWith('/productions/prod-1')) return json(productionOne);
    if (url.endsWith('/me')) return json(currentPerson);
    if (url.endsWith(`/rehearsals/${rehearsal.id}`)) return json(rehearsal);
    if (url.includes(`/rehearsal-attendances/${own.id}/respond`) && init?.method === 'PUT') {
      const body = JSON.parse(String(init.body));
      responds.push(body);
      current = { ...current, status: body.status, remarks: body.remarks };
      return json(current);
    }
    if (url.includes(`/rehearsals/${rehearsal.id}/attendances`)) return json([current]);
    if (url.includes('/participants')) return json([]);
    if (url.includes('/comments')) return json([]);
    throw new Error(`Unmocked fetch: ${url}`);
  }) as jest.Mock;

  return responds;
}

function renderScreen() {
  render(
    <QueryClientProvider client={createQueryClient()}>
      <RehearsalAttendanceScreen />
    </QueryClientProvider>
  );
}

/**
 * PR #7 R9 §3 (docs/20-RehearsalManagementScreen.md §8.1 / §8.2): the
 * detail screen shows the member's current answer; before the deadline an
 * answered member can change it (answer and note) through
 * 「回答を変更する」; after the deadline - or once the Rehearsal has started
 * - the answer is shown without any way to change it. The Backend checks
 * the same rules (RespondRehearsalAttendanceUseCase).
 */
describe('Attendance detail: changing an answer already given', () => {
  it('lets an answered member change the answer and note before the deadline', async () => {
    mockRehearsalId = 'rehearsal-1';
    const rehearsal = { ...rehearsalScheduleAdjustment, status: 'SCHEDULED', response_deadline: '2099-01-01T00:00:00+09:00' };
    const responds = serve(rehearsal, { ...scheduleAdjustmentRoster[0], status: 'AVAILABLE', remarks: '19時から' });
    renderScreen();

    await waitFor(() => expect(screen.getByTestId('attendance-my-status')).toHaveTextContent('参加可能'));
    expect(screen.getByTestId('attendance-my-remarks')).toHaveTextContent(/19時から/);
    expect(screen.queryByTestId('attendance-respond-UNAVAILABLE')).toBeNull();

    fireEvent.press(screen.getByTestId('attendance-change-answer'));
    await waitFor(() => expect(screen.getByTestId('attendance-respond-UNAVAILABLE')).toBeVisible());
    expect(screen.getByTestId('attendance-remarks-input')).toHaveDisplayValue('19時から');

    fireEvent.changeText(screen.getByTestId('attendance-remarks-input'), '仕事が入りました');
    await waitFor(() => expect(screen.getByTestId('attendance-remarks-input')).toHaveDisplayValue('仕事が入りました'));
    fireEvent.press(screen.getByTestId('attendance-respond-UNAVAILABLE'));

    await waitFor(() => expect(responds).toEqual([{ status: 'UNAVAILABLE', remarks: '仕事が入りました' }]));
    await waitFor(() => expect(screen.getByTestId('attendance-my-status')).toHaveTextContent('参加不可'));
    expect(screen.queryByTestId('attendance-respond-UNAVAILABLE')).toBeNull();
    expect(screen.getByTestId('attendance-change-answer')).toBeVisible();
  });

  it('closes the change form without sending anything on 変更しない', async () => {
    mockRehearsalId = 'rehearsal-1';
    const rehearsal = { ...rehearsalScheduleAdjustment, status: 'SCHEDULED', response_deadline: '2099-01-01T00:00:00+09:00' };
    const responds = serve(rehearsal, { ...scheduleAdjustmentRoster[0], status: 'AVAILABLE', remarks: null });
    renderScreen();

    await waitFor(() => expect(screen.getByTestId('attendance-change-answer')).toBeVisible());
    fireEvent.press(screen.getByTestId('attendance-change-answer'));
    await waitFor(() => expect(screen.getByTestId('attendance-change-answer-cancel')).toBeVisible());
    fireEvent.press(screen.getByTestId('attendance-change-answer-cancel'));

    await waitFor(() => expect(screen.queryByTestId('attendance-respond-AVAILABLE')).toBeNull());
    expect(responds).toEqual([]);
  });

  it('shows the answer without a way to change it once the deadline has passed', async () => {
    mockRehearsalId = 'rehearsal-1';
    const rehearsal = { ...rehearsalScheduleAdjustment, status: 'SCHEDULED', response_deadline: '2020-01-01T00:00:00+09:00' };
    serve(rehearsal, { ...scheduleAdjustmentRoster[0], status: 'AVAILABLE', remarks: null });
    renderScreen();

    await waitFor(() => expect(screen.getByTestId('attendance-my-status')).toHaveTextContent('参加可能'));
    expect(screen.getByTestId('attendance-response-deadline-passed')).toBeVisible();
    expect(screen.queryByTestId('attendance-change-answer')).toBeNull();
    expect(screen.queryByTestId('attendance-respond-UNAVAILABLE')).toBeNull();
  });

  it('shows a confirmed answer without a way to change it once the Rehearsal has started', async () => {
    mockRehearsalId = 'rehearsal-3';
    serve(rehearsalActive, {
      ...attendanceConfirmationRoster[0],
      id: 'attendance-3',
      rehearsal_id: 'rehearsal-3',
      person_id: 'person-1',
      status: 'ATTENDING',
    });
    renderScreen();

    await waitFor(() => expect(screen.getByTestId('attendance-my-status')).toHaveTextContent('出席'));
    expect(screen.queryByTestId('attendance-change-answer')).toBeNull();
    expect(screen.queryByTestId('attendance-respond-NOT_ATTENDING')).toBeNull();
  });
});
