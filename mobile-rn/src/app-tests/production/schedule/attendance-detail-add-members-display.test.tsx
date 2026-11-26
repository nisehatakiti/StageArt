import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { currentPerson, productionOne, rehearsalScheduleAdjustment } from '@/features/attendance/__fixtures__/attendanceFixtures';

/**
 * Split into its own single-render file (see
 * attendance-detail-add-members-add.test.tsx's own docblock): a 2nd
 * renderRouter() call within the same file was found to reliably lose
 * its `fetch` mock entirely in this environment, unrelated to this
 * screen's own logic.
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

const participantInactive = {
  id: 'participant-3',
  production_id: 'prod-1',
  subject_type: 'PERSON',
  subject_id: 'person-3',
  participant_type: 'CAST',
  status: 'INACTIVE',
  created_at: '',
  updated_at: '',
};

describe('Attendance detail: 未選択メンバーの表示', () => {
  it('作成時に未選択だったACTIVEメンバーのみ追加候補として表示される（既存参加者・非ACTIVEは表示されない）', async () => {
    global.fetch = jest.fn(async (input: unknown) => {
      const url = String(input);

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
        const body = [participantCastSelected, participantStaffUnselected, participantInactive];
        return { ok: true, status: 200, text: async () => JSON.stringify(body), json: async () => body } as Response;
      }
      if (url.includes('/rehearsals/rehearsal-1/attendances')) {
        return { ok: true, status: 200, text: async () => JSON.stringify([existingAttendance]), json: async () => [existingAttendance] } as Response;
      }

      throw new Error(`Unmocked fetch: ${url}`);
    });

    renderRouter('src/app', { initialUrl: '/production/prod-1/schedule/attendance/rehearsal-1' });

    await waitFor(() => expect(screen.getByTestId('unselected-member-person-2')).toBeVisible());
    expect(screen.queryByTestId('unselected-member-person-1')).toBeNull();
    expect(screen.queryByTestId('unselected-member-person-3')).toBeNull();
  });
});
