import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

const nonManagerProduction = {
  id: 'prod-1',
  project_id: 'proj-1',
  name: '第10回公演',
  title_heading: null,
  status: 'ACTIVE',
  slug: 'prod-1-slug',
  published_at: null,
  primary_manager_person_id: 'person-1',
  created_at: '',
  updated_at: '',
  is_primary_manager: false,
  delegate_role: null,
  description: null,
  description_published_at: null,
  flyer_url: null,
  flyer_published_at: null,
  venue_name: null,
  venue_published_at: null,
  schedule_start_date: null,
  schedule_end_date: null,
  schedule_published_at: null,
  script_credit: null,
  direction_credit: null,
  script_direction_published_at: null,
  member_info_published_at: null,
  capacity: 100,
  performance_common_remarks: null,
};

/**
 * アンケート実装指示書 §33/AC-42: client-side gating for parity with
 * every other Production-management screen - the real enforcement stays
 * server-side (QuestionnaireCapability::MANAGE), this only prevents a
 * non-manager from being offered controls the Backend would reject
 * anyway. Kept in its own file - this codebase's established
 * single-test-per-file convention for these Web route tests avoids
 * QueryClient cache bleed between tests that would otherwise render the
 * SAME `/productions/prod-1` query key with different fixtures.
 */
describe('Web アンケート管理: 権限', () => {
  it('shows a forbidden message for a non-manager, non-delegate user', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1') && !u.includes('questionnaire'), status: 200, body: nonManagerProduction },
      { test: (u) => u.endsWith('/productions/prod-1/questionnaire'), status: 404, body: { message: 'Questionnaire not found' } },
    ]);

    renderRouter('src/app', { initialUrl: '/productions/prod-1/questionnaire' });

    await waitFor(() => expect(screen.getByTestId('production-questionnaire-forbidden')).toBeVisible());
  });
});
