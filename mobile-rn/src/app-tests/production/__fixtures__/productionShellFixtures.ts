import type { NotificationFact, Production, ProductionAccountingSummary, PushPreference } from '@/types/api';

export const productionOne: Production = {
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

export const productionWithTitleHeading: Production = {
  ...productionOne,
  title_heading: '旗揚げ公演',
};

export const currentPerson = {
  id: 'person-1',
  word_press_user_id: 1,
  email_verified: true,
  family_name: '秦',
  given_name: '良輔',
};

export const accountingFull: ProductionAccountingSummary = {
  production_id: 'prod-1',
  has_budget: true,
  active_budget_id: 'budget-1',
  total_budget: 300000,
  has_actual: true,
  total_actual: -180000,
  total_variance: -480000,
  currency: 'JPY',
};

export const accountingNotSet: ProductionAccountingSummary = {
  production_id: 'prod-1',
  has_budget: false,
  active_budget_id: null,
  total_budget: null,
  has_actual: false,
  total_actual: 0,
  total_variance: null,
  currency: 'JPY',
};

export const notificationsTwo: NotificationFact[] = [
  {
    id: 'notif-2',
    type: 'timetable_version_published',
    production_id: 'prod-1',
    rehearsal_id: 'rehearsal-2',
    timetable_id: 'timetable-2',
    version: 3,
    published_by: 'person-1',
    published_at: '2026-08-15T09:30:00+09:00',
    change_summary: '集合時間を30分繰り上げました。',
    created_at: '2026-08-15T09:30:00+09:00',
    is_read: false,
  },
  {
    id: 'notif-1',
    type: 'timetable_version_published',
    production_id: 'prod-1',
    rehearsal_id: 'rehearsal-1',
    timetable_id: 'timetable-1',
    version: 1,
    published_by: 'person-1',
    published_at: '2026-08-10T12:00:00+09:00',
    change_summary: null,
    created_at: '2026-08-10T12:00:00+09:00',
    is_read: true,
  },
];

export const pushPreferenceOn: PushPreference = { enabled: true, updated_at: '2026-08-18T00:00:00+09:00' };
export const pushPreferenceOff: PushPreference = { enabled: false, updated_at: '2026-08-18T00:00:00+09:00' };

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

/** Routes by URL suffix, most-specific match first. Mirrors
 * src/app/production/schedule/__fixtures__/scheduleFixtures.ts's helper
 * of the same name/shape. */
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
