import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

import { ApiClient } from '@/api/client';
import { mockFetchRoutes } from '@/features/attendance/__fixtures__/attendanceFixtures';
import CreateRehearsalScreen from '../../../app/(app)/production/[id]/schedule/attendance/create';

const mockApiClient = new ApiClient(() => 'mock-access-token');

/**
 * Mocked directly (rather than going through the real AuthProvider boot
 * sequence) since this screen's fields render before AuthContext's own
 * async `/auth/refresh` effect resolves - unlike every other screen this
 * test file's own sibling tests exercise, nothing here is gated on an
 * authenticated GET query, so there is no observable signal to wait on.
 * A ready apiClient sidesteps that timing entirely; the behavior under
 * test (create -> optionally confirm) does not depend on AuthContext's
 * own boot logic.
 */
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

function participant(id: string, subjectId: string, participantType: 'CAST' | 'STAFF' = 'CAST') {
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

const memberA = participant('participant-a', 'person-a');
const memberB = participant('participant-b', 'person-b');
const memberC = participant('participant-c', 'person-c');
const staffD = participant('participant-d', 'person-d', 'STAFF');
const staffE = participant('participant-e', 'person-e', 'STAFF');

function renderCreate() {
  const queryClient = new QueryClient();
  return render(
    <QueryClientProvider client={queryClient}>
      <CreateRehearsalScreen />
    </QueryClientProvider>
  );
}

function isCheckboxChecked(subjectId: string): boolean {
  const style = screen.getByTestId(`rehearsal-create-member-checkbox-${subjectId}`).props.style;
  const flattened = Array.isArray(style) ? Object.assign({}, ...style.filter(Boolean)) : style;
  return flattened?.backgroundColor === '#4a3f7a';
}

/**
 * UI-only: checked state is asserted directly on each checkbox, without
 * ever calling handleSubmit. Placed first in the file (before any test
 * that submits) since this environment was found to reliably corrupt a
 * render several submit-triggering tests deep into a shared test file
 * (a `fetch` mock that stops being invoked at all past a certain point,
 * confirmed unrelated to this screen's own logic - every one of these
 * assertions and every submit-based test below pass individually and in
 * the first few positions of any ordering tried). Keeping these
 * assertions submit-free and first avoids the issue entirely rather than
 * chasing its exact mechanism further.
 */
describe('稽古作成: 参加メンバー選択の初期状態とチェック操作（UI状態のみ）', () => {
  it('初期状態でProductionメンバー全員のチェックボックスが選択済みになっている', async () => {
    mockFetchRoutes([{ test: (u) => u.endsWith('/productions/prod-1/participants'), status: 200, body: [memberA, memberB, memberC] }]);

    renderCreate();

    await waitFor(() => expect(screen.getByTestId('rehearsal-create-member-person-a')).toBeVisible());
    expect(isCheckboxChecked('person-a')).toBe(true);
    expect(isCheckboxChecked('person-b')).toBe(true);
    expect(isCheckboxChecked('person-c')).toBe(true);
  });

  it('メンバー行を押すとそのメンバーだけチェックが外れる', async () => {
    mockFetchRoutes([{ test: (u) => u.endsWith('/productions/prod-1/participants'), status: 200, body: [memberA, memberB, memberC] }]);

    renderCreate();

    await waitFor(() => expect(screen.getByTestId('rehearsal-create-member-person-b')).toBeVisible());
    fireEvent.press(screen.getByTestId('rehearsal-create-member-person-b'));

    await waitFor(() => expect(isCheckboxChecked('person-b')).toBe(false));
    expect(isCheckboxChecked('person-a')).toBe(true);
    expect(isCheckboxChecked('person-c')).toBe(true);
  });

  it('全選択解除を押すと全員のチェックが外れる', async () => {
    mockFetchRoutes([{ test: (u) => u.endsWith('/productions/prod-1/participants'), status: 200, body: [memberA, memberB, memberC] }]);

    renderCreate();

    await waitFor(() => expect(screen.getByTestId('rehearsal-create-member-person-a')).toBeVisible());
    fireEvent.press(screen.getByTestId('rehearsal-create-deselect-all'));

    await waitFor(() => expect(isCheckboxChecked('person-a')).toBe(false));
    expect(isCheckboxChecked('person-b')).toBe(false);
    expect(isCheckboxChecked('person-c')).toBe(false);
  });

  it('全選択解除の後に全選択を押すと全員のチェックが再び入る', async () => {
    mockFetchRoutes([{ test: (u) => u.endsWith('/productions/prod-1/participants'), status: 200, body: [memberA, memberB, memberC] }]);

    renderCreate();

    await waitFor(() => expect(screen.getByTestId('rehearsal-create-member-person-a')).toBeVisible());
    fireEvent.press(screen.getByTestId('rehearsal-create-deselect-all'));
    await waitFor(() => expect(isCheckboxChecked('person-a')).toBe(false));

    fireEvent.press(screen.getByTestId('rehearsal-create-select-all'));

    await waitFor(() => expect(isCheckboxChecked('person-a')).toBe(true));
    expect(isCheckboxChecked('person-b')).toBe(true);
    expect(isCheckboxChecked('person-c')).toBe(true);
  });

  it('初期状態でCASTのみ選択済みで、STAFFは未選択になっている', async () => {
    mockFetchRoutes([{ test: (u) => u.endsWith('/productions/prod-1/participants'), status: 200, body: [memberA, memberB, memberC, staffD, staffE] }]);

    renderCreate();

    await waitFor(() => expect(screen.getByTestId('rehearsal-create-member-person-d')).toBeVisible());
    expect(isCheckboxChecked('person-a')).toBe(true);
    expect(isCheckboxChecked('person-b')).toBe(true);
    expect(isCheckboxChecked('person-c')).toBe(true);
    expect(isCheckboxChecked('person-d')).toBe(false);
    expect(isCheckboxChecked('person-e')).toBe(false);
  });

  it('STAFFを個別にチェックできる', async () => {
    mockFetchRoutes([{ test: (u) => u.endsWith('/productions/prod-1/participants'), status: 200, body: [memberA, memberB, memberC, staffD, staffE] }]);

    renderCreate();

    await waitFor(() => expect(screen.getByTestId('rehearsal-create-member-person-d')).toBeVisible());
    fireEvent.press(screen.getByTestId('rehearsal-create-member-person-d'));

    await waitFor(() => expect(isCheckboxChecked('person-d')).toBe(true));
    expect(isCheckboxChecked('person-e')).toBe(false);
    expect(isCheckboxChecked('person-a')).toBe(true);
  });

  it('全選択でCAST/STAFFを含む全対象者が選択される', async () => {
    mockFetchRoutes([{ test: (u) => u.endsWith('/productions/prod-1/participants'), status: 200, body: [memberA, memberB, memberC, staffD, staffE] }]);

    renderCreate();

    await waitFor(() => expect(screen.getByTestId('rehearsal-create-member-person-d')).toBeVisible());
    fireEvent.press(screen.getByTestId('rehearsal-create-select-all'));

    await waitFor(() => expect(isCheckboxChecked('person-d')).toBe(true));
    expect(isCheckboxChecked('person-e')).toBe(true);
    expect(isCheckboxChecked('person-a')).toBe(true);
    expect(isCheckboxChecked('person-b')).toBe(true);
    expect(isCheckboxChecked('person-c')).toBe(true);
  });

  it('全選択解除でCAST/STAFFを含む全対象者の選択が外れる', async () => {
    mockFetchRoutes([{ test: (u) => u.endsWith('/productions/prod-1/participants'), status: 200, body: [memberA, memberB, memberC, staffD, staffE] }]);

    renderCreate();

    await waitFor(() => expect(screen.getByTestId('rehearsal-create-member-person-a')).toBeVisible());
    fireEvent.press(screen.getByTestId('rehearsal-create-deselect-all'));

    await waitFor(() => expect(isCheckboxChecked('person-a')).toBe(false));
    expect(isCheckboxChecked('person-b')).toBe(false);
    expect(isCheckboxChecked('person-c')).toBe(false);
    expect(isCheckboxChecked('person-d')).toBe(false);
    expect(isCheckboxChecked('person-e')).toBe(false);
  });
});

describe('稽古作成: ステータス選択', () => {
  beforeEach(() => {
    mockReplace.mockClear();
  });

  it('「調整」選択時（デフォルト）は作成のみ呼び出し、confirmは呼ばない', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1/participants'), status: 200, body: [] },
      { test: (u) => u.endsWith('/productions/prod-1/rehearsals'), status: 201, body: newRehearsal },
    ]);

    renderCreate();

    await waitFor(() => expect(screen.getByTestId('rehearsal-create-title')).toBeVisible());
    fireEvent.changeText(screen.getByTestId('rehearsal-create-title'), '新しい稽古');
    await waitFor(() => expect(screen.getByTestId('rehearsal-create-title').props.value).toBe('新しい稽古'));
    fireEvent.press(screen.getByTestId('rehearsal-create-submit'));

    await waitFor(() => expect(mockReplace).toHaveBeenCalledWith('/production/prod-1/schedule/attendance/rehearsal-new'));

    const calls = (global.fetch as jest.Mock).mock.calls;
    expect(calls.some(([url]: [string]) => url.endsWith('/productions/prod-1/rehearsals'))).toBe(true);
    expect(calls.some(([url]: [string]) => url.endsWith('/confirm'))).toBe(false);
  });

  it('「確定」選択時は作成後にconfirmを呼び出す', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1/participants'), status: 200, body: [] },
      { test: (u) => u.endsWith('/productions/prod-1/rehearsals'), status: 201, body: newRehearsal },
      { test: (u) => u.endsWith('/rehearsals/rehearsal-new/confirm'), status: 200, body: { ...newRehearsal, status: 'CONFIRMED' } },
    ]);

    renderCreate();

    await waitFor(() => expect(screen.getByTestId('rehearsal-create-title')).toBeVisible());
    fireEvent.changeText(screen.getByTestId('rehearsal-create-title'), '新しい稽古');
    await waitFor(() => expect(screen.getByTestId('rehearsal-create-title').props.value).toBe('新しい稽古'));
    fireEvent.press(screen.getByTestId('rehearsal-create-status-confirmed'));
    await waitFor(() =>
      expect(screen.getByTestId('rehearsal-create-status-confirmed').props.style).toMatchObject({ backgroundColor: '#4a3f7a' })
    );
    fireEvent.press(screen.getByTestId('rehearsal-create-submit'));

    await waitFor(() => expect(mockReplace).toHaveBeenCalledWith('/production/prod-1/schedule/attendance/rehearsal-new'));

    const calls = (global.fetch as jest.Mock).mock.calls;
    expect(
      calls.some(([url, options]: [string, RequestInit]) => url.endsWith('/productions/prod-1/rehearsals') && options?.method === 'POST')
    ).toBe(true);
    expect(
      calls.some(([url, options]: [string, RequestInit]) => url.endsWith('/rehearsals/rehearsal-new/confirm') && options?.method === 'POST')
    ).toBe(true);
  });
});

function findCreateRehearsalBody() {
  const call = (global.fetch as jest.Mock).mock.calls.find(
    ([url, options]: [string, RequestInit]) => url.endsWith('/productions/prod-1/rehearsals') && options?.method === 'POST'
  );
  return JSON.parse(call[1].body as string);
}

describe('稽古作成: 参加メンバー選択（送信内容の確認）', () => {
  beforeEach(() => {
    mockReplace.mockClear();
  });

  it('初期状態でProductionメンバー全員が選択されており、全員がperson_idsとして送信される', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1/participants'), status: 200, body: [memberA, memberB, memberC] },
      { test: (u) => u.endsWith('/productions/prod-1/rehearsals'), status: 201, body: newRehearsal },
    ]);

    renderCreate();

    await waitFor(() => expect(screen.getByTestId('rehearsal-create-member-person-a')).toBeVisible());
    fireEvent.changeText(screen.getByTestId('rehearsal-create-title'), '新しい稽古');
    await waitFor(() => expect(screen.getByTestId('rehearsal-create-title').props.value).toBe('新しい稽古'));
    fireEvent.press(screen.getByTestId('rehearsal-create-submit'));

    await waitFor(() => expect(mockReplace).toHaveBeenCalledWith('/production/prod-1/schedule/attendance/rehearsal-new'));

    expect(findCreateRehearsalBody().person_ids).toEqual(expect.arrayContaining(['person-a', 'person-b', 'person-c']));
    expect(findCreateRehearsalBody().person_ids).toHaveLength(3);
  });

  it('Bのチェックを外すと、A/Cのみがperson_idsとして送信される', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1/participants'), status: 200, body: [memberA, memberB, memberC] },
      { test: (u) => u.endsWith('/productions/prod-1/rehearsals'), status: 201, body: newRehearsal },
    ]);

    renderCreate();

    await waitFor(() => expect(screen.getByTestId('rehearsal-create-member-person-b')).toBeVisible());
    fireEvent.press(screen.getByTestId('rehearsal-create-member-person-b'));

    fireEvent.changeText(screen.getByTestId('rehearsal-create-title'), '新しい稽古');
    await waitFor(() => expect(screen.getByTestId('rehearsal-create-title').props.value).toBe('新しい稽古'));
    fireEvent.press(screen.getByTestId('rehearsal-create-submit'));

    await waitFor(() => expect(mockReplace).toHaveBeenCalledWith('/production/prod-1/schedule/attendance/rehearsal-new'));

    const sentPersonIds = findCreateRehearsalBody().person_ids;
    expect(sentPersonIds).toEqual(expect.arrayContaining(['person-a', 'person-c']));
    expect(sentPersonIds).not.toEqual(expect.arrayContaining(['person-b']));
    expect(sentPersonIds).toHaveLength(2);
  });
});
