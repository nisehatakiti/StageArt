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
 * PR #7 R11 (docs/20-RehearsalManagementScreen.md §24): on a device outside
 * Japan the editor shows the Asia/Tokyo date and time - the same as the
 * list screens - and saving without changes sends the same time back, so
 * repeated saves never move an item.
 *
 * The expected values hold on a device in any timezone; R11 ran this file
 * with the process timezone set to Asia/Tokyo, UTC, America/New_York,
 * America/Los_Angeles and Pacific/Kiritimati (TZ at Jest start - Jest
 * does not apply a change to process.env.TZ made inside a test file).
 */
describe('Timetable editor: Asia/Tokyo times on any device', () => {
  it('shows 10:00 JST as 10:00 and saves it back as 10:00+09:00', async () => {
    const items = [
      { ...baseItem, id: 'item-a', title: '場当たり', start_date_time: '2026-08-20T10:00:00+09:00', end_date_time: '2026-08-20T12:00:00+09:00' },
      { ...baseItem, id: 'item-b', title: '年越し', start_date_time: '2026-12-31T15:30:00Z', end_date_time: null },
    ];
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

    render(
      <QueryClientProvider client={createQueryClient()}>
        <TimetableEditorScreen />
      </QueryClientProvider>
    );

    await waitFor(() => expect(screen.getByTestId('timetable-target-rehearsal-2')).toBeVisible());
    fireEvent.press(screen.getByTestId('timetable-target-rehearsal-2'));

    await waitFor(() => expect(screen.getByTestId('timetable-item-title-1').props.value).toBe('年越し'));
    expect(screen.getByTestId('timetable-item-date-0').props.value).toBe('2026-08-20');
    expect(screen.getByTestId('timetable-item-start-0').props.value).toBe('10:00');
    expect(screen.getByTestId('timetable-item-end-0').props.value).toBe('12:00');
    expect(screen.getByTestId('timetable-item-date-1').props.value).toBe('2027-01-01');
    expect(screen.getByTestId('timetable-item-start-1').props.value).toBe('00:30');

    fireEvent.press(screen.getByTestId('timetable-save'));
    await waitFor(() => expect(screen.getByTestId('timetable-save-success')).toBeVisible());

    const posted = writes
      .filter((write) => write.method === 'POST' && write.url.endsWith('/timetable-items'))
      .map((write) => write.body as { start_date_time: string; end_date_time: string | null });
    expect(posted.map((body) => [body.start_date_time, body.end_date_time])).toEqual([
      ['2026-08-20T10:00:00+09:00', '2026-08-20T12:00:00+09:00'],
      ['2027-01-01T00:30:00+09:00', null],
    ]);
  });
});
