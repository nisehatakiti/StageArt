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

/**
 * PR #7 R8 (2-5): the first timetable of a 予定. 「保存」 starts a DRAFT
 * Version, adds the items in order and publishes the DRAFT (DRAFT ->
 * PUBLISHED) in one operation; there is no 「内容を公開する」 button. 備考 is
 * optional (sent as null when empty). Incomplete rows are refused before
 * any request is sent.
 */
describe('Timetable editor: first save of a 予定 timetable', () => {
  it('validates, then creates a DRAFT, adds the item and publishes it', async () => {
    const calls: { method: string; url: string; body: unknown }[] = [];
    let draftCreated = false;

    global.fetch = jest.fn(async (input: unknown, init?: RequestInit) => {
      const url = String(input);
      const method = init?.method ?? 'GET';
      const body = init?.body ? JSON.parse(String(init.body)) : null;
      calls.push({ method, url, body });

      if (url.endsWith('/productions/prod-1')) return json(200, productionOne);
      if (url.includes('/productions/prod-1/rehearsals')) return json(200, [rehearsalConfirmed]);
      if (url.endsWith('/rehearsals/rehearsal-2/timetable/draft')) {
        return draftCreated
          ? json(200, { id: 'draft-1' })
          : json(404, { code: 'stageart_timetable_not_found', message: 'No DRAFT.', data: { status: 404 } });
      }
      if (url.endsWith('/rehearsals/rehearsal-2/timetable-versions') && method === 'POST') {
        draftCreated = true;
        return json(201, { id: 'draft-1' });
      }
      if (url.endsWith('/rehearsals/rehearsal-2/timetable-items/draft')) return json(200, []);
      if (url.endsWith('/rehearsals/rehearsal-2/timetable-items') && method === 'GET') return json(200, []);
      if (url.endsWith('/rehearsals/rehearsal-2/timetable-items') && method === 'POST') return json(201, { id: 'item-1', ...body });
      if (url.endsWith('/timetable-versions/draft-1/publish')) return json(200, { id: 'draft-1', status: 'PUBLISHED' });

      throw new Error(`Unmocked fetch: ${method} ${url}`);
    }) as jest.Mock;

    render(
      <QueryClientProvider client={createQueryClient()}>
        <TimetableEditorScreen />
      </QueryClientProvider>
    );

    await waitFor(() => expect(screen.getByTestId('timetable-target-rehearsal-2')).toBeVisible());
    fireEvent.press(screen.getByTestId('timetable-target-rehearsal-2'));
    await waitFor(() => expect(screen.getByTestId('timetable-target-rehearsal-2').props.accessibilityState).toMatchObject({ selected: true }));

    await waitFor(() => expect(screen.getByTestId('timetable-item-add')).toBeVisible());
    expect(screen.queryByTestId('timetable-draft-notice')).toBeNull();
    expect(screen.queryByText('内容を公開する')).toBeNull();

    fireEvent.press(screen.getByTestId('timetable-item-add'));
    await waitFor(() => expect(screen.getByTestId('timetable-item-title-0')).toBeVisible());
    // The new row starts on the 予定's own date.
    expect(screen.getByTestId('timetable-item-date-0').props.value).toBe('2026-08-20');

    // Incomplete row: refused locally, nothing sent.
    fireEvent.press(screen.getByTestId('timetable-save'));
    await waitFor(() => expect(screen.getByTestId('timetable-save-validation')).toBeVisible());
    expect(calls.some((call) => call.method !== 'GET')).toBe(false);

    fireEvent.changeText(screen.getByTestId('timetable-item-title-0'), '小屋入り');
    fireEvent.changeText(screen.getByTestId('timetable-item-start-0'), '09:00');
    await waitFor(() => expect(screen.getByTestId('timetable-item-title-0').props.value).toBe('小屋入り'));

    fireEvent.press(screen.getByTestId('timetable-save'));

    await waitFor(() => expect(screen.getByTestId('timetable-save-success')).toBeVisible());

    const writes = calls.filter((call) => call.method !== 'GET');
    const path = (url: string) => url.slice(url.search(/\/(rehearsals|timetable-versions)\//));
    expect(writes.map((call) => `${call.method} ${path(call.url)}`)).toEqual([
      'POST /rehearsals/rehearsal-2/timetable-versions',
      'POST /rehearsals/rehearsal-2/timetable-items',
      'POST /timetable-versions/draft-1/publish',
    ]);
    expect(writes[1].body).toEqual({
      title: '小屋入り',
      start_date_time: '2026-08-20T09:00:00+09:00',
      end_date_time: null,
      venue: null,
      notes: null,
      display_order: 1,
    });
  });
});
