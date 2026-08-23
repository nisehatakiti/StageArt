import type { Rehearsal, RehearsalAttendance } from '@/types/api';

export const productionOne = {
  id: 'prod-1',
  project_id: 'proj-1',
  name: '○○公演2026',
  title_heading: null,
  status: 'ACTIVE',
  primary_manager_person_id: 'person-1',
  created_at: '',
  updated_at: '',
  is_primary_manager: true,
  delegate_role: null,
};

export const currentPerson = {
  id: 'person-1',
  word_press_user_id: 1,
  email_verified: true,
  family_name: '秦',
  given_name: '良輔',
};

export const rehearsalScheduleAdjustment: Rehearsal = {
  id: 'rehearsal-1',
  production_id: 'prod-1',
  title: '第1回稽古（日程調整）',
  description: null,
  start_date_time: '2026-08-20T10:00:00+09:00',
  end_date_time: '2026-08-20T12:00:00+09:00',
  timezone: 'Asia/Tokyo',
  location: '稽古場A',
  status: 'DRAFT',
  created_at: '',
  updated_at: '',
};

export const rehearsalConfirmed: Rehearsal = {
  ...rehearsalScheduleAdjustment,
  id: 'rehearsal-2',
  title: '第2回稽古（出欠確定）',
  status: 'CONFIRMED',
};

export const rehearsals: Rehearsal[] = [rehearsalScheduleAdjustment, rehearsalConfirmed];

export const scheduleAdjustmentRoster: RehearsalAttendance[] = [
  {
    id: 'attendance-1',
    rehearsal_id: 'rehearsal-1',
    person_id: 'person-1',
    phase: 'SCHEDULE_ADJUSTMENT',
    status: 'UNANSWERED',
    created_at: '',
    updated_at: '',
  },
  {
    id: 'attendance-2',
    rehearsal_id: 'rehearsal-1',
    person_id: 'person-2',
    phase: 'SCHEDULE_ADJUSTMENT',
    status: 'AVAILABLE',
    created_at: '',
    updated_at: '',
  },
];

export const attendanceConfirmationRoster: RehearsalAttendance[] = [
  {
    id: 'attendance-3',
    rehearsal_id: 'rehearsal-2',
    person_id: 'person-1',
    phase: 'ATTENDANCE_CONFIRMATION',
    status: 'ATTENDING',
    created_at: '',
    updated_at: '',
  },
];

/**
 * StageArt Authentication Phase 5: AuthProvider now calls a real
 * POST /auth/refresh at boot whenever a Refresh Token is in SecureStore
 * (see AuthContext's session-restore effect) - every renderRouter()-
 * mounted test therefore needs a working response for it, or the app
 * gets stuck in `refreshing`/falls back to `unauthenticated` instead of
 * reaching whatever screen the test actually cares about. Supplied as a
 * fallback (checked only if the caller's own `routes` didn't already
 * match), so a test that specifically wants to exercise refresh failure
 * can still override it by passing its own /auth/refresh route.
 */
const DEFAULT_ROUTES: { test: (url: string) => boolean; status: number; body: unknown }[] = [
  {
    test: (url) => url.endsWith('/auth/refresh'),
    status: 200,
    body: { access_token: 'mock-refreshed-access-token', token_type: 'Bearer', expires_in: 3600 },
  },
  {
    test: (url) => url.endsWith('/me'),
    status: 200,
    body: { id: 'person-1', word_press_user_id: 1, email_verified: true, family_name: '秦', given_name: '良輔' },
  },
];

/** Routes by URL suffix, most-specific match first. */
export function mockFetchRoutes(routes: { test: (url: string) => boolean; status: number; body: unknown }[]) {
  global.fetch = jest.fn(async (input: unknown) => {
    const url = String(input);
    const route = routes.find((candidate) => candidate.test(url)) ?? DEFAULT_ROUTES.find((candidate) => candidate.test(url));

    if (!route) {
      throw new Error(`Unmocked fetch: ${url}`);
    }

    return {
      ok: route.status >= 200 && route.status < 300,
      status: route.status,
      text: async () => JSON.stringify(route.body),
      json: async () => route.body,
    } as Response;
  });
}
