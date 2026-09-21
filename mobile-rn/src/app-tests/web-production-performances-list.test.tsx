import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

const baseProduction = {
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
  is_primary_manager: true,
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

const performanceA = {
  id: 'perf-1',
  production_id: 'prod-1',
  performance_date: '2026-10-10',
  start_time: '13:00:00',
  end_time: '15:00:00',
  capacity: 100,
  remarks: null,
  symbol: null,
  status: 'PUBLISHED',
  created_at: '',
  updated_at: '',
};

/**
 * StageArt Phase 2 Performance基盤 (Performance基盤 実装指示書 §22): the
 * 公演回管理 screen - confirms the Performance list renders from
 * GET /productions/{id}/performances with 公演日/開演時刻/終演予定時刻/定員/
 * Status all visible. Kept as a single-test file (not combined with the
 * empty/forbidden-state cases) matching this codebase's established
 * convention against multi-`it()`/multi-`renderRouter()` files, which
 * corrupt the RTL `screen` singleton across tests within the same file
 * (see web-production-participants-bulk-update.test.tsx's sibling
 * -toggle/-submit-on/-submit-off split for the same reasoning).
 */
describe('Web 公演回管理: 一覧表示', () => {
  it('shows the Performance list for a Primary Manager', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: baseProduction },
      { test: (u) => u.endsWith('/productions/prod-1/performances'), status: 200, body: [performanceA] },
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [] },
      { test: (u) => u.endsWith('/projects'), status: 200, body: [] },
    ]);

    renderRouter('src/app', { initialUrl: '/productions/prod-1/performances' });

    await waitFor(() => expect(screen.getByTestId('performance-row-perf-1')).toBeVisible());

    expect(screen.getByText('2026-10-10')).toBeVisible();
    expect(screen.getByText('13:00')).toBeVisible();
    expect(screen.getByText('15:00')).toBeVisible();
    expect(screen.getByText('100')).toBeVisible();
    expect(screen.getByText('公開中')).toBeVisible();
  });
});
