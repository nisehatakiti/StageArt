import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

import { ApiClient } from '@/api/client';
import { mockFetchRoutes } from '@/features/attendance/__fixtures__/attendanceFixtures';
import CreateRehearsalScreen from '../../../app/(app)/production/[id]/schedule/attendance/create';

const mockApiClient = new ApiClient(() => 'mock-access-token');

jest.mock('@/auth/AuthContext', () => ({
  useAuth: () => ({ apiClient: mockApiClient, status: 'authenticated' }),
}));

const mockReplace = jest.fn();

jest.mock('expo-router', () => ({
  useRouter: () => ({ replace: mockReplace }),
  useLocalSearchParams: () => ({ id: 'prod-1' }),
}));

const newRehearsal = {
  id: 'rehearsal-new',
  production_id: 'prod-1',
  title: '新しい稽古',
  description: null,
  start_date_time: null,
  end_date_time: null,
  timezone: 'Asia/Tokyo',
  location: null,
  status: 'SCHEDULED',
  response_deadline: '2026-09-19T18:00:00+09:00',
  created_at: '',
  updated_at: '',
};

/**
 * StageArt Phase 7 (Rehearsal仕様整合) §4: "回答期限" - kept in its own
 * file (single-test-per-file), matching this directory's own established
 * convention for `create.tsx` submit-triggering tests, since this
 * sibling test file's own docblock discloses that this Jest environment
 * corrupts a render several submit-triggering tests deep into one shared
 * file.
 */
describe('稽古作成: 回答期限', () => {
  it('date/timeの両方を入力した場合のみresponse_deadlineとして送信される', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1/participants'), status: 200, body: [] },
      { test: (u) => u.endsWith('/productions/prod-1/rehearsals'), status: 201, body: newRehearsal },
    ]);

    const queryClient = new QueryClient();
    render(
      <QueryClientProvider client={queryClient}>
        <CreateRehearsalScreen />
      </QueryClientProvider>
    );

    await waitFor(() => expect(screen.getByTestId('rehearsal-create-title')).toBeVisible());
    fireEvent.changeText(screen.getByTestId('rehearsal-create-title'), '新しい稽古');
    fireEvent.changeText(screen.getByTestId('rehearsal-create-deadline-date'), '2026-09-19');
    fireEvent.changeText(screen.getByTestId('rehearsal-create-deadline-time'), '18:00');
    await waitFor(() => expect(screen.getByTestId('rehearsal-create-deadline-time').props.value).toBe('18:00'));
    fireEvent.press(screen.getByTestId('rehearsal-create-submit'));

    await waitFor(() => expect(mockReplace).toHaveBeenCalledWith('/production/prod-1/schedule/attendance/rehearsal-new'));

    const call = (global.fetch as jest.Mock).mock.calls.find(
      ([url, options]: [string, RequestInit]) => url.endsWith('/productions/prod-1/rehearsals') && options?.method === 'POST'
    );
    const body = JSON.parse(call[1].body as string);
    expect(body.response_deadline).toBe('2026-09-19T18:00:00+09:00');
  });
});
