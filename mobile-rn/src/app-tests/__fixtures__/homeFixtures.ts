import type { MyDashboard, Organization, Production, Project } from '@/types/api';

export const orgOne: Organization = {
  id: 'org-1',
  name: '○○演劇団',
  type: null,
  description: null,
  status: 'ACTIVE',
  slug: null,
  published_at: null,
  created_at: '2026-01-01T00:00:00+09:00',
  updated_at: '2026-01-01T00:00:00+09:00',
  current_person_role: 'OWNER',
  follower_count: null,
};

export const orgTwo: Organization = {
  ...orgOne,
  id: 'org-2',
  name: '△△企画',
  current_person_role: 'MEMBER',
};

export const projectOne: Project = {
  id: 'proj-1',
  organization_id: 'org-1',
  name: null,
  status: 'ACTIVE',
  created_at: '',
  updated_at: '',
  current_person_role: 'OWNER',
};

export const projectTwo: Project = {
  ...projectOne,
  id: 'proj-2',
  organization_id: 'org-2',
  current_person_role: 'MEMBER',
};

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

export const productionTwo: Production = {
  id: 'prod-2',
  project_id: 'proj-2',
  name: '△△公演',
  title_heading: null,
  status: 'PLANNING',
  slug: null,
  published_at: null,
  primary_manager_person_id: 'person-2',
  created_at: '',
  updated_at: '',
  is_primary_manager: false,
  delegate_role: 'REHEARSAL_MANAGER',
};

export const myDashboardEmpty: MyDashboard = {
  upcoming_rehearsals: [],
  notifications: [],
  followed_organizations_feed: [],
};

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
    body: { id: 'person-1', word_press_user_id: 1, email_verified: true, family_name: '舞台', given_name: '芸術' },
  },
];

/** Routes by URL suffix, most-specific match first (so /productions/{id}
 * is checked before the bare /productions list). */
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

/**
 * Not exported as a mockSecureStoreAuthenticated() helper: jest.mock()
 * calls are hoisted syntactically within the file that calls them (see
 * Phase 5.1's documented hoisting constraint), and referencing an
 * imported out-of-scope variable inside the factory is only permitted
 * when its own name is "mock"-prefixed. Every test file below therefore
 * repeats this exact inline jest.mock('expo-secure-store', ...) call
 * itself, matching the established pattern in navigation.test.tsx /
 * login-flow.test.tsx / production-shell.test.tsx.
 */
