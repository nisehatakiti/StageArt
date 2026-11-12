import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { currentPerson, productionOne, rehearsalScheduleAdjustment } from '@/features/attendance/__fixtures__/attendanceFixtures';

/**
 * Split into its own single-render file: a 2nd renderRouter() call
 * within the same test file (even one exercising an entirely separate
 * scenario, with its own fresh `global.fetch` mock) was found to
 * reliably lose its `fetch` mock entirely partway through this
 * environment's async data-fetching chain - the same environment quirk
 * documented in attendance-rehearsal-create.test.tsx (plain render(),
 * not renderRouter()) and worked around there the same way: one render
 * per file, rather than chasing the underlying mechanism further.
 */
jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

const existingAttendance = {
  id: 'attendance-1',
  rehearsal_id: 'rehearsal-1',
  person_id: 'person-1',
  phase: 'SCHEDULE_ADJUSTMENT',
  status: 'UNANSWERED',
  created_at: '',
  updated_at: '',
};

const participantCastSelected = {
  id: 'participant-1',
  production_id: 'prod-1',
  subject_type: 'PERSON',
  subject_id: 'person-1',
  participant_type: 'CAST',
  status: 'ACTIVE',
  created_at: '',
  updated_at: '',
};

const participantStaffUnselected = {
  id: 'participant-2',
  production_id: 'prod-1',
  subject_type: 'PERSON',
  subject_id: 'person-2',
  participant_type: 'STAFF',
  status: 'ACTIVE',
  created_at: '',
  updated_at: '',
};

describe('Attendance detail: 未選択メンバーの追加操作', () => {
  it('未選択メンバーを選択して追加すると、そのPersonがRehearsalAttendance対象になる', async () => {
    let sentBody: unknown = null;
    let addedPersonIds: string[] = [];

    global.fetch = jest.fn(async (input: unknown, init?: RequestInit) => {
      const url = String(input);
      const method = init?.method ?? 'GET';

      if (url.endsWith('/auth/refresh')) {
        return {
          ok: true,
          status: 200,
          text: async () => JSON.stringify({ access_token: 'refreshed-token', token_type: 'Bearer', expires_in: 3600 }),
        } as Response;
      }
      if (url.endsWith('/productions/prod-1')) {
        return { ok: true, status: 200, text: async () => JSON.stringify(productionOne), json: async () => productionOne } as Response;
      }
      if (url.endsWith('/me')) {
        return { ok: true, status: 200, text: async () => JSON.stringify(currentPerson), json: async () => currentPerson } as Response;
      }
      if (url.endsWith('/rehearsals/rehearsal-1')) {
        return {
          ok: true,
          status: 200,
          text: async () => JSON.stringify(rehearsalScheduleAdjustment),
          json: async () => rehearsalScheduleAdjustment,
        } as Response;
      }
      if (url.endsWith('/productions/prod-1/participants')) {
        const body = [participantCastSelected, participantStaffUnselected];
        return { ok: true, status: 200, text: async () => JSON.stringify(body), json: async () => body } as Response;
      }
      if (method === 'POST' && url.includes('/rehearsals/rehearsal-1/attendances')) {
        const parsedBody = init?.body ? JSON.parse(String(init.body)) : null;
        sentBody = parsedBody;
        addedPersonIds = parsedBody?.person_ids ?? [];
        const created = addedPersonIds.map((personId, index) => ({
          id: `attendance-new-${index}`,
          rehearsal_id: 'rehearsal-1',
          person_id: personId,
          phase: 'SCHEDULE_ADJUSTMENT',
          status: 'UNANSWERED',
          created_at: '',
          updated_at: '',
        }));
        return { ok: true, status: 201, text: async () => JSON.stringify(created), json: async () => created } as Response;
      }
      if (method === 'GET' && url.includes('/rehearsals/rehearsal-1/attendances')) {
        const rows = [
          existingAttendance,
          ...addedPersonIds.map((personId, index) => ({
            id: `attendance-new-${index}`,
            rehearsal_id: 'rehearsal-1',
            person_id: personId,
            phase: 'SCHEDULE_ADJUSTMENT',
            status: 'UNANSWERED',
            created_at: '',
            updated_at: '',
          })),
        ];
        return { ok: true, status: 200, text: async () => JSON.stringify(rows), json: async () => rows } as Response;
      }

      throw new Error(`Unmocked fetch: ${url} (${method})`);
    });

    renderRouter('src/app', { initialUrl: '/production/prod-1/schedule/attendance/rehearsal-1' });

    await waitFor(() => expect(screen.getByTestId('unselected-member-person-2')).toBeVisible());
    fireEvent.press(screen.getByTestId('unselected-member-person-2'));

    await waitFor(() => {
      const style = screen.getByTestId('unselected-member-checkbox-person-2').props.style;
      const flattened = Array.isArray(style) ? Object.assign({}, ...style.filter(Boolean)) : style;
      expect(flattened.backgroundColor).toBe('#4a3f7a');
    });
    fireEvent.press(screen.getByTestId('unselected-members-add'));

    await waitFor(() => expect(sentBody).toEqual({ person_ids: ['person-2'] }));
    await waitFor(() => expect(screen.queryByTestId('unselected-member-person-2')).toBeNull());
  });
});
