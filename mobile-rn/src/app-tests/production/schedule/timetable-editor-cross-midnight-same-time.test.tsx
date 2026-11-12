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
 * PR #7 R12 (docs/20-RehearsalManagementScreen.md §25): the timetable
 * editor does not save the same start and end. Moved out of
 * timetable-editor-cross-midnight.test.tsx in the R12 follow-up (one
 * submit-triggering test per file, as in this directory: after the other
 * test's save it timed out while rendering).
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

describe('Timetable editor: items crossing midnight', () => {
  it('does not save the same start and end time', async () => {
    const writes = mockApi([
      { ...baseItem, id: 'item-a', title: 'バラシ', start_date_time: '2026-11-10T23:30:00+09:00', end_date_time: '2026-11-11T00:30:00+09:00' },
    ]);
    await openEditor();

    await waitFor(() => expect(screen.getByTestId('timetable-item-end-0').props.value).toBe('00:30'));
    // R12 follow-up: the end date is kept, so the same time needs the end
    // date set back to the start date too (11/11 23:30 alone is 24 hours).
    fireEvent.changeText(screen.getByTestId('timetable-item-end-date-0'), '2026-11-10');
    await waitFor(() => expect(screen.getByTestId('timetable-item-end-date-0').props.value).toBe('2026-11-10'));
    fireEvent.changeText(screen.getByTestId('timetable-item-end-0'), '23:30');
    await waitFor(() => expect(screen.getByTestId('timetable-item-end-0').props.value).toBe('23:30'));

    fireEvent.press(screen.getByTestId('timetable-save'));

    await waitFor(() => expect(screen.getByTestId('timetable-save-validation')).toHaveTextContent(/1行目：開始と終了が同じ時刻です/));
    expect(writes).toEqual([]);
  });
});
