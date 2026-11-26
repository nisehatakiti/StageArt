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

const draftItem = {
  id: 'draft-item-1',
  timetable_id: 'draft-7',
  title: '場当たり',
  description: null,
  start_date_time: '2026-08-20T13:00:00+09:00',
  end_date_time: '2026-08-20T15:00:00+09:00',
  display_order: 1,
  category: null,
  venue: '本舞台',
  participant_type: null,
  target_person_ids: [],
  notes: '照明合わせあり',
  created_at: '',
  updated_at: '',
};

/**
 * PR #7 R8 (2-5): editing when a DRAFT already exists (e.g. an earlier
 * save stopped part-way). The editor shows the DRAFT's items, and 「保存」
 * reuses that DRAFT (no new Version), replaces its items with the edited
 * list and publishes it.
 */
describe('Timetable editor: save with an existing DRAFT', () => {
  it('edits the DRAFT items and publishes the same DRAFT', async () => {
    const calls: { method: string; url: string; body: unknown }[] = [];

    global.fetch = jest.fn(async (input: unknown, init?: RequestInit) => {
      const url = String(input);
      const method = init?.method ?? 'GET';
      const body = init?.body ? JSON.parse(String(init.body)) : null;
      calls.push({ method, url, body });

      if (url.endsWith('/productions/prod-1')) return json(200, productionOne);
      if (url.includes('/productions/prod-1/rehearsals')) return json(200, [rehearsalConfirmed]);
      if (url.endsWith('/rehearsals/rehearsal-2/timetable/draft')) return json(200, { id: 'draft-7' });
      if (url.endsWith('/rehearsals/rehearsal-2/timetable-items/draft')) return json(200, [draftItem]);
      if (url.endsWith('/timetable-items/draft-item-1') && method === 'DELETE') return json(200, null);
      if (url.endsWith('/rehearsals/rehearsal-2/timetable-items') && method === 'POST') return json(201, { id: 'item-2', ...body });
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

    await waitFor(() => expect(screen.getByTestId('timetable-item-title-0').props.value).toBe('場当たり'));
    expect(screen.getByTestId('timetable-draft-notice')).toBeVisible();
    expect(screen.getByTestId('timetable-item-date-0').props.value).toBe('2026-08-20');
    expect(screen.getByTestId('timetable-item-start-0').props.value).toBe('13:00');
    expect(screen.getByTestId('timetable-item-end-0').props.value).toBe('15:00');
    expect(screen.getByTestId('timetable-item-notes-0').props.value).toBe('照明合わせあり');

    fireEvent.changeText(screen.getByTestId('timetable-item-notes-0'), '');
    await waitFor(() => expect(screen.getByTestId('timetable-item-notes-0').props.value).toBe(''));

    fireEvent.press(screen.getByTestId('timetable-save'));
    await waitFor(() => expect(screen.getByTestId('timetable-save-success')).toBeVisible());

    const path = (url: string) => url.slice(url.search(/\/(rehearsals|timetable-versions|timetable-items)\//));
    const writes = calls.filter((call) => call.method !== 'GET');
    expect(writes.map((call) => `${call.method} ${path(call.url)}`)).toEqual([
      'DELETE /timetable-items/draft-item-1',
      'POST /rehearsals/rehearsal-2/timetable-items',
      'POST /timetable-versions/draft-7/publish',
    ]);
    expect(writes[1].body).toEqual({
      title: '場当たり',
      start_date_time: '2026-08-20T13:00:00+09:00',
      end_date_time: '2026-08-20T15:00:00+09:00',
      venue: '本舞台',
      notes: null,
      display_order: 1,
    });
  });
});
