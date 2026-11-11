import { QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react-native';

import { ApiClient } from '@/api/client';
import { createQueryClient } from '@/api/queryClient';
import { mockFetchRoutes, productionOne, rehearsalScheduleAdjustment } from '@/features/attendance/__fixtures__/attendanceFixtures';
import type { Rehearsal } from '@/types/api';
import RehearsalListScreen from '../../../app/(app)/production/[id]/schedule/attendance/index';

const mockApiClient = new ApiClient(() => 'mock-access-token');

/**
 * The screen is rendered on its own (as attendance-rehearsal-create.test
 * does) rather than through renderRouter(): several renderRouter() renders
 * in one file stop rendering the list after the first one in this
 * environment, unrelated to the screen itself.
 */
jest.mock('@/auth/AuthContext', () => ({
  useAuth: () => ({ apiClient: mockApiClient, status: 'authenticated' }),
}));

jest.mock('expo-router', () => ({
  useRouter: () => ({ push: jest.fn() }),
  useLocalSearchParams: () => ({ id: 'prod-1' }),
}));

const PAST_START = '2020-01-01T10:00:00+09:00';
const PAST_END = '2020-01-01T12:00:00+09:00';
const FUTURE_START = '2099-01-01T10:00:00+09:00';
const FUTURE_END = '2099-01-01T12:00:00+09:00';

function rehearsal(id: string, status: string, start: string, end: string): Rehearsal {
  return { ...rehearsalScheduleAdjustment, id, status, start_date_time: start, end_date_time: end };
}

const list: Rehearsal[] = [
  rehearsal('scheduled-future', 'SCHEDULED', FUTURE_START, FUTURE_END),
  rehearsal('scheduled-past', 'SCHEDULED', PAST_START, PAST_END),
  rehearsal('confirmed-past', 'CONFIRMED', PAST_START, PAST_END),
  rehearsal('cancelled-past', 'CANCELLED', PAST_START, PAST_END),
];

const MESSAGE = '開始予定時刻を過ぎています。確定または中止を選択してください。';

function renderList(production: object) {
  mockFetchRoutes([
    { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: production },
    { test: (u) => u.endsWith('/productions/prod-1/rehearsals'), status: 200, body: list },
  ]);

  return render(
    <QueryClientProvider client={createQueryClient()}>
      <RehearsalListScreen />
    </QueryClientProvider>
  );
}

function productionFetched(): boolean {
  return (global.fetch as jest.Mock).mock.calls.some(([url]: [string]) => String(url).endsWith('/productions/prod-1'));
}

/**
 * PR #7 R10 (docs/20-RehearsalManagementScreen.md §23.2): a manager sees
 * which 調整中 Rehearsals are past their start time - only those - and a
 * member does not. Nothing is confirmed or started automatically.
 */
describe('Attendance: Rehearsal list - overdue 調整中', () => {
  it('PrimaryManager: only the 調整中 Rehearsal past its start time is marked', async () => {
    renderList(productionOne);

    await waitFor(() => expect(screen.getByTestId('rehearsal-overdue-scheduled-past')).toHaveTextContent(MESSAGE));
    expect(screen.getAllByTestId(/rehearsal-row-/)).toHaveLength(4);
    expect(screen.queryByTestId('rehearsal-overdue-scheduled-future')).toBeNull();
    expect(screen.queryByTestId('rehearsal-overdue-confirmed-past')).toBeNull();
    expect(screen.queryByTestId('rehearsal-overdue-cancelled-past')).toBeNull();
    // Still shown as 調整中: nothing moved it on.
    expect(screen.getByTestId('rehearsal-status-scheduled-past')).toHaveTextContent('調整中');
    const calls = (global.fetch as jest.Mock).mock.calls.map(([url]: [string]) => String(url));
    expect(calls.some((url) => /\/confirm|\/activate|\/complete|\/cancel/.test(url))).toBe(false);
  });

  it('REHEARSAL_MANAGER delegate: sees the notice', async () => {
    renderList({ ...productionOne, is_primary_manager: false, delegate_roles: ['REHEARSAL_MANAGER'] });

    await waitFor(() => expect(screen.getByTestId('rehearsal-overdue-scheduled-past')).toBeVisible());
  });

  it('member (not a rehearsal manager): no overdue notice', async () => {
    renderList({ ...productionOne, is_primary_manager: false, delegate_roles: ['TICKET_MANAGER'] });

    await waitFor(() => expect(screen.getAllByTestId(/rehearsal-row-/)).toHaveLength(4));
    await waitFor(() => expect(productionFetched()).toBe(true));
    expect(screen.queryByTestId(/rehearsal-overdue-/)).toBeNull();
    expect(screen.queryByText(MESSAGE)).toBeNull();
  });
});
