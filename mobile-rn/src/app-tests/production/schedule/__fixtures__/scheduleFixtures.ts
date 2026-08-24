import type { Participant, Production, ScheduleComment, TimetableItem } from '@/types/api';

export const productionOne: Production = {
  id: 'prod-1',
  project_id: 'proj-1',
  name: '○○公演2026',
  title_heading: null,
  status: 'ACTIVE',
  slug: null,
  published_at: null,
  primary_manager_person_id: 'person-1',
  created_at: '',
  updated_at: '',
  is_primary_manager: true,
  delegate_role: null,
};

export const staffItem: TimetableItem = {
  id: 'item-staff',
  timetable_id: 'tt-1',
  title: '照明シュート',
  description: '舞台上で照明のシュートを行う',
  start_date_time: '2026-08-18T10:00:00+09:00',
  end_date_time: '2026-08-18T12:00:00+09:00',
  display_order: 1,
  category: 'シュート',
  venue: '舞台',
  participant_type: 'LIGHTING',
  target_person_ids: [],
  notes: '暗転あり',
  created_at: '',
  updated_at: '',
};

export const castItem: TimetableItem = {
  id: 'item-cast',
  timetable_id: 'tt-1',
  title: '第一場 通し稽古',
  description: null,
  start_date_time: '2026-08-18T14:00:00+09:00',
  end_date_time: null,
  display_order: 2,
  category: null,
  venue: '稽古場',
  participant_type: null,
  target_person_ids: ['person-1'],
  notes: null,
  created_at: '',
  updated_at: '',
};

export const currentPerson = {
  id: 'person-1',
  word_press_user_id: 1,
  email_verified: true,
  family_name: '秦',
  given_name: '良輔',
};

export const participants: Participant[] = [
  {
    id: 'part-1',
    production_id: 'prod-1',
    subject_type: 'PERSON',
    subject_id: 'person-1',
    participant_type: 'CAST',
    status: 'ACTIVE',
    created_at: '',
    updated_at: '',
  },
];

export const comments: ScheduleComment[] = [
  {
    id: 'comment-1',
    rehearsal_id: null,
    timetable_item_id: 'item-staff',
    author_person_id: 'person-2',
    body: '少し遅れます',
    created_at: '2026-08-18T09:00:00+09:00',
    updated_at: '2026-08-18T09:00:00+09:00',
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
