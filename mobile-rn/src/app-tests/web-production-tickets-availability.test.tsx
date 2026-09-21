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

const openPerformance = {
  performance_id: 'perf-open',
  performance_date: '2026-10-10',
  start_time: '13:00:00',
  performance_status: 'PUBLISHED',
  is_ticket_published: true,
  sales_start_at: '2026-09-10T00:00:00+09:00',
  sales_end_at: '2026-10-09T23:00:00+09:00',
  is_sales_open: true,
};

const closedPerformance = {
  performance_id: 'perf-closed',
  performance_date: '2026-10-11',
  start_time: '18:00:00',
  performance_status: 'PUBLISHED',
  is_ticket_published: true,
  sales_start_at: '2026-09-10T00:00:00+09:00',
  sales_end_at: '2026-10-10T23:00:00+09:00',
  is_sales_open: false,
};

/**
 * StageArt チケット管理 (Ticket全体像整備): "公演スケジュールごとの販売可能
 * 状態を確認できる" - GET /productions/{id}/performance-ticket-
 * availability の結果を表示する。
 */
describe('Web チケット管理: 公演スケジュールごとの販売可能状態', () => {
  it('shows 販売中/販売不可 per Performance from the availability endpoint', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: baseProduction },
      { test: (u) => u.endsWith('/productions/prod-1/tickets'), status: 200, body: [] },
      {
        test: (u) => u.endsWith('/productions/prod-1/performance-ticket-availability'),
        status: 200,
        body: [openPerformance, closedPerformance],
      },
    ]);

    renderRouter('src/app', { initialUrl: '/productions/prod-1/tickets' });

    await waitFor(() => expect(screen.getByTestId('ticket-availability-row-perf-open')).toBeVisible());
    expect(screen.getByTestId('ticket-availability-status-perf-open')).toHaveTextContent('販売中');
    expect(screen.getByTestId('ticket-availability-status-perf-closed')).toHaveTextContent('販売不可');
  });
});
