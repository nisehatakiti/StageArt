import { QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

import { ApiClient } from '@/api/client';
import { createQueryClient } from '@/api/queryClient';
import { productionOne, rehearsalConfirmed } from '@/features/attendance/__fixtures__/attendanceFixtures';

import TimetableEditorScreen from '../../../app/(app)/production/[id]/schedule/timetable';

const mockApiClient = new ApiClient(() => 'mock-access-token');

jest.mock('@/auth/AuthContext', () => ({
  useAuth: () => ({ apiClient: mockApiClient, status: 'authenticated' }),
}));

jest.mock('expo-router', () => ({
  useRouter: () => ({ push: jest.fn(), back: jest.fn(), replace: jest.fn() }),
  useLocalSearchParams: () => ({ id: 'prod-1' }),
}));

const json = (status: number, body: unknown) =>
  ({ ok: status < 400, status, text: async () => JSON.stringify(body), json: async () => body }) as Response;

const baseItem = {
  timetable_id: 'draft-7',
  description: null,
  category: null,
  venue: null,
  participant_type: null,
  target_person_ids: [],
  notes: null,
  created_at: '',
  updated_at: '',
};

/**
 * PR #7 R12 follow-up (docs/20-RehearsalManagementScreen.md §25.4): an end
 * time moved before the start is refused - the end date is not moved to
 * another day. (One submit-triggering test per file, as in this directory.)
 */
function mockApi(items: unknown[]) {
  const writes: { method: string; url: string; body: unknown }[] = [];
  global.fetch = jest.fn(async (input: unknown, init?: RequestInit) => {
    const url = String(input);
    const method = init?.method ?? 'GET';
    const body = init?.body ? JSON.parse(String(init.body)) : null;
    if (method !== 'GET') writes.push({ method, url, body });

    if (url.endsWith('/productions/prod-1')) return json(200, productionOne);
    if (url.includes('/productions/prod-1/rehearsals')) return json(200, [rehearsalConfirmed]);
    if (url.endsWith('/rehearsals/rehearsal-2/timetable/draft')) return json(200, { id: 'draft-7' });
    if (url.endsWith('/rehearsals/rehearsal-2/timetable-items/draft')) return json(200, items);
    if (url.includes('/timetable-items/item-') && method === 'DELETE') return json(200, null);
    if (url.endsWith('/rehearsals/rehearsal-2/timetable-items') && method === 'POST') return json(201, { id: 'new', ...body });
    if (url.endsWith('/timetable-versions/draft-7/publish')) return json(200, { id: 'draft-7', status: 'PUBLISHED' });

    throw new Error(`Unmocked fetch: ${method} ${url}`);
  }) as jest.Mock;
  return writes;
}

async function openEditor() {
  render(
    <QueryClientProvider client={createQueryClient()}>
      <TimetableEditorScreen />
    </QueryClientProvider>
  );
  await waitFor(() => expect(screen.getByTestId('timetable-target-rehearsal-2')).toBeVisible());
  fireEvent.press(screen.getByTestId('timetable-target-rehearsal-2'));
}

const items = [
  { ...baseItem, id: 'item-a', display_order: 1, title: '仕込み', start_date_time: '2026-11-10T09:00:00+09:00', end_date_time: '2026-11-12T18:00:00+09:00' },
  { ...baseItem, id: 'item-b', display_order: 2, title: '劇場借り', start_date_time: '2026-11-10T09:00:00+09:00', end_date_time: '2026-11-13T09:00:00+09:00' },
  { ...baseItem, id: 'item-c', display_order: 3, title: '機材レンタル', start_date_time: '2026-11-09T10:00:00+09:00', end_date_time: '2026-11-15T17:00:00+09:00' },
  { ...baseItem, id: 'item-d', display_order: 4, title: 'バラシ', start_date_time: '2026-11-10T23:30:00+09:00', end_date_time: '2026-11-11T00:30:00+09:00' },
  { ...baseItem, id: 'item-e', display_order: 5, title: '受付', start_date_time: '2026-11-10T17:00:00+09:00', end_date_time: '2026-11-10T18:00:00+09:00' },
];

describe('Timetable editor: items longer than a day', () => {
  it('refuses an end time moved before the start instead of moving the end date', async () => {
    const writes = mockApi([items[4]]);
    await openEditor();

    await waitFor(() => expect(screen.getByTestId('timetable-item-end-0').props.value).toBe('18:00'));
    fireEvent.changeText(screen.getByTestId('timetable-item-end-0'), '01:00');
    await waitFor(() => expect(screen.getByTestId('timetable-item-end-0').props.value).toBe('01:00'));
    expect(screen.getByTestId('timetable-item-end-date-0').props.value).toBe('2026-11-10');

    fireEvent.press(screen.getByTestId('timetable-save'));
    await waitFor(() =>
      expect(screen.getByTestId('timetable-save-validation')).toHaveTextContent('1行目：終了が開始より前になっています。終了日または終了時刻を直してください。')
    );
    expect(writes).toEqual([]);
  });
});
