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
  display_order: 1,
  category: null,
  venue: null,
  participant_type: null,
  target_person_ids: [],
  notes: null,
  created_at: '',
  updated_at: '',
};

/**
 * PR #7 R12 (docs/20-RehearsalManagementScreen.md §25): the timetable editor
 * keeps the next-day end of an item crossing midnight across load / edit /
 * save, shows 「翌日」, and does not save the same start and end time.
 * The expected values hold in any device timezone (R12 ran it with TZ set
 * to Asia/Tokyo, UTC, America/New_York, America/Los_Angeles and
 * Pacific/Kiritimati at Jest start).
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

describe('Timetable editor: items crossing midnight', () => {
  it('shows 翌日, keeps the next-day end on an unchanged save, and puts an edited earlier end on the next day', async () => {
    const writes = mockApi([
      { ...baseItem, id: 'item-a', title: 'バラシ', start_date_time: '2026-11-10T23:30:00+09:00', end_date_time: '2026-11-11T00:30:00+09:00' },
      { ...baseItem, id: 'item-b', title: '撤収', start_date_time: '2026-11-10T22:00:00+09:00', end_date_time: '2026-11-10T23:00:00+09:00' },
    ]);
    await openEditor();

    await waitFor(() => expect(screen.getByTestId('timetable-item-title-1').props.value).toBe('撤収'));
    expect(screen.getByTestId('timetable-item-date-0').props.value).toBe('2026-11-10');
    expect(screen.getByTestId('timetable-item-start-0').props.value).toBe('23:30');
    expect(screen.getByTestId('timetable-item-end-0').props.value).toBe('00:30');
    expect(screen.getByTestId('timetable-item-end-day-0')).toHaveTextContent('翌日');
    expect(screen.queryByTestId('timetable-item-end-day-1')).toBeNull();

    expect(screen.getByTestId('timetable-item-end-date-0').props.value).toBe('2026-11-11');
    expect(screen.getByTestId('timetable-item-end-date-1').props.value).toBe('2026-11-10');

    // Row 2 now ends at 01:30 the next day. R12 follow-up: the end date is
    // the row's own field and is changed explicitly (R12 moved it to the
    // next day by itself when the end time was earlier than the start).
    fireEvent.changeText(screen.getByTestId('timetable-item-end-date-1'), '2026-11-11');
    fireEvent.changeText(screen.getByTestId('timetable-item-end-1'), '01:30');
    await waitFor(() => expect(screen.getByTestId('timetable-item-end-day-1')).toHaveTextContent('翌日'));

    fireEvent.press(screen.getByTestId('timetable-save'));
    await waitFor(() => expect(screen.getByTestId('timetable-save-success')).toBeVisible());

    expect(posted(writes)).toEqual([
      ['バラシ', '2026-11-10T23:30:00+09:00', '2026-11-11T00:30:00+09:00'],
      ['撤収', '2026-11-10T22:00:00+09:00', '2026-11-11T01:30:00+09:00'],
    ]);
  });
});
