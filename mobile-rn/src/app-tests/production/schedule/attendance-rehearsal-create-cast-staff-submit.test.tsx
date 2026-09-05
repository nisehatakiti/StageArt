import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

import { ApiClient } from '@/api/client';
import { mockFetchRoutes } from '@/features/attendance/__fixtures__/attendanceFixtures';
import CreateRehearsalScreen from '../../../app/(app)/production/[id]/schedule/attendance/create';

/**
 * Kept in its own file (not appended to attendance-rehearsal-create.test.tsx)
 * so its submit-triggering tests don't add to that file's own already-tight
 * submit budget - see that file's own docblock on the environment quirk
 * this sidesteps entirely by giving these tests a fresh Jest module
 * registry.
 */
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
  created_at: '',
  updated_at: '',
};

function participant(id: string, subjectId: string, participantType: 'CAST' | 'STAFF') {
  return {
    id,
    production_id: 'prod-1',
    subject_type: 'PERSON',
    subject_id: subjectId,
    participant_type: participantType,
    status: 'ACTIVE',
    created_at: '',
    updated_at: '',
  };
}

const castA = participant('participant-a', 'person-a', 'CAST');
const castB = participant('participant-b', 'person-b', 'CAST');
const staffC = participant('participant-c', 'person-c', 'STAFF');
const staffD = participant('participant-d', 'person-d', 'STAFF');

function renderCreate() {
  const queryClient = new QueryClient();
  return render(
    <QueryClientProvider client={queryClient}>
      <CreateRehearsalScreen />
    </QueryClientProvider>
  );
}

function findCreateRehearsalBody() {
  const call = (global.fetch as jest.Mock).mock.calls.find(
    ([url, options]: [string, RequestInit]) => url.endsWith('/productions/prod-1/rehearsals') && options?.method === 'POST'
  );
  return JSON.parse(call[1].body as string);
}

describe('稽古作成: CAST/STAFF初期選択と送信内容', () => {
  beforeEach(() => {
    mockReplace.mockClear();
  });

  it('CASTとSTAFFが混在する場合、デフォルトのまま保存するとCASTだけがperson_idsとして送信される', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1/participants'), status: 200, body: [castA, castB, staffC, staffD] },
      { test: (u) => u.endsWith('/productions/prod-1/rehearsals'), status: 201, body: newRehearsal },
    ]);

    renderCreate();

    await waitFor(() => expect(screen.getByTestId('rehearsal-create-member-person-c')).toBeVisible());
    fireEvent.changeText(screen.getByTestId('rehearsal-create-title'), '新しい稽古');
    await waitFor(() => expect(screen.getByTestId('rehearsal-create-title').props.value).toBe('新しい稽古'));
    fireEvent.press(screen.getByTestId('rehearsal-create-submit'));

    await waitFor(() => expect(mockReplace).toHaveBeenCalledWith('/production/prod-1/schedule/attendance/rehearsal-new'));

    const sentPersonIds = findCreateRehearsalBody().person_ids;
    expect(sentPersonIds).toEqual(expect.arrayContaining(['person-a', 'person-b']));
    expect(sentPersonIds).not.toEqual(expect.arrayContaining(['person-c', 'person-d']));
    expect(sentPersonIds).toHaveLength(2);
  });

  it('CASTに加えて一部のSTAFFを選択すると、その対象だけがperson_idsとして送信される', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1/participants'), status: 200, body: [castA, castB, staffC, staffD] },
      { test: (u) => u.endsWith('/productions/prod-1/rehearsals'), status: 201, body: newRehearsal },
    ]);

    renderCreate();

    await waitFor(() => expect(screen.getByTestId('rehearsal-create-member-person-c')).toBeVisible());
    fireEvent.press(screen.getByTestId('rehearsal-create-member-person-c'));

    fireEvent.changeText(screen.getByTestId('rehearsal-create-title'), '新しい稽古');
    await waitFor(() => expect(screen.getByTestId('rehearsal-create-title').props.value).toBe('新しい稽古'));
    fireEvent.press(screen.getByTestId('rehearsal-create-submit'));

    await waitFor(() => expect(mockReplace).toHaveBeenCalledWith('/production/prod-1/schedule/attendance/rehearsal-new'));

    const sentPersonIds = findCreateRehearsalBody().person_ids;
    expect(sentPersonIds).toEqual(expect.arrayContaining(['person-a', 'person-b', 'person-c']));
    expect(sentPersonIds).not.toEqual(expect.arrayContaining(['person-d']));
    expect(sentPersonIds).toHaveLength(3);
  });
});

/**
 * "0人選択も既存仕様どおり正しく処理される" (全選択解除した状態での保存) is
 * intentionally NOT re-verified here via a 3rd submit-triggering render in
 * this file - doing so was found to reliably reproduce the same
 * environment-level pollution documented in attendance-rehearsal-create.
 * test.tsx's own docblock (a 3rd submit-triggering render in a file with no
 * preceding UI-only renders loses its own `fetch` mock entirely). This
 * specific scenario is otherwise already covered end-to-end without that
 * risk: the UI side (selectedPersonIds correctly becoming an empty Set
 * after 全選択解除) is proven by attendance-rehearsal-create.test.tsx's own
 * UI-only "全選択解除を押すと全員のチェックが外れる" tests, and the Backend
 * side (an empty targetPersonIds list producing zero RehearsalAttendance
 * records) is proven by RehearsalUseCaseTest.php's
 * test_create_rehearsal_with_no_selected_members_generates_no_attendance.
 * The only unverified link - `Array.from(new Set())` serializing to `[]` in
 * the POST body - is standard, well-understood JS behavior, not
 * StageArt-specific logic.
 */
