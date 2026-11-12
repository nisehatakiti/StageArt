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
 * PR #7 R12 follow-up (docs/20-RehearsalManagementScreen.md §25.4): items
 * longer than a day (only creatable through the API before) keep their end
 * date in the editor - unchanged, with only the end time changed, with the
 * end date changed and with the start date changed. The expected values
 * hold in any device timezone (run with TZ set to Asia/Tokyo, UTC,
 * America/New_York, America/Los_Angeles and Pacific/Kiritimati at Jest
 * start).
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

const posted = (writes: { method: string; url: string; body: unknown }[]) =>
  writes
    .filter((write) => write.method === 'POST' && write.url.endsWith('/timetable-items'))
    .map((write) => {
      const body = write.body as { title: string; start_date_time: string; end_date_time: string | null };
      return [body.title, body.start_date_time, body.end_date_time];
    });

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
  it('keeps the end dates through end-time, end-date and start-date edits', async () => {
    const writes = mockApi(items);
    await openEditor();

    await waitFor(() => expect(screen.getByTestId('timetable-item-title-4').props.value).toBe('受付'));
    expect(['0', '1', '2', '3', '4'].map((i) => screen.getByTestId(`timetable-item-end-date-${i}`).props.value)).toEqual([
      '2026-11-12',
      '2026-11-13',
      '2026-11-15',
      '2026-11-11',
      '2026-11-10',
    ]);
    expect(screen.getByTestId('timetable-item-end-day-0')).toHaveTextContent('11/12終了');
    expect(screen.getByTestId('timetable-item-end-day-1')).toHaveTextContent('11/13終了');
    expect(screen.getByTestId('timetable-item-end-day-2')).toHaveTextContent('11/15終了');
    expect(screen.getByTestId('timetable-item-end-day-3')).toHaveTextContent('翌日');
    expect(screen.queryByTestId('timetable-item-end-day-4')).toBeNull();

    // 仕込み (2 days): only the end time -> still ends 11/12.
    fireEvent.changeText(screen.getByTestId('timetable-item-end-0'), '08:00');
    // 劇場借り (3 days): only the end date.
    fireEvent.changeText(screen.getByTestId('timetable-item-end-date-1'), '2026-11-14');
    // 機材レンタル (6 days): the start date one day later -> the end date too.
    fireEvent.changeText(screen.getByTestId('timetable-item-date-2'), '2026-11-10');
    await waitFor(() => expect(screen.getByTestId('timetable-item-end-date-2').props.value).toBe('2026-11-16'));
    expect(screen.getByTestId('timetable-item-end-0').props.value).toBe('08:00');
    expect(screen.getByTestId('timetable-item-end-date-0').props.value).toBe('2026-11-12');
    expect(screen.getByTestId('timetable-item-end-day-1')).toHaveTextContent('11/14終了');

    fireEvent.press(screen.getByTestId('timetable-save'));
    await waitFor(() => expect(screen.getByTestId('timetable-save-success')).toBeVisible());

    expect(posted(writes)).toEqual([
      ['仕込み', '2026-11-10T09:00:00+09:00', '2026-11-12T08:00:00+09:00'],
      ['劇場借り', '2026-11-10T09:00:00+09:00', '2026-11-14T09:00:00+09:00'],
      ['機材レンタル', '2026-11-10T10:00:00+09:00', '2026-11-16T17:00:00+09:00'],
      ['バラシ', '2026-11-10T23:30:00+09:00', '2026-11-11T00:30:00+09:00'],
      ['受付', '2026-11-10T17:00:00+09:00', '2026-11-10T18:00:00+09:00'],
    ]);
  });
});
