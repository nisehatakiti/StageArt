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

const savedSalesSettings = {
  production_id: 'prod-1',
  ticket_publication_at: '2026-09-01T00:00:00+09:00',
  ticket_sales_start_at: '2026-09-10T00:00:00+09:00',
  ticket_sales_end_rule: 'DAY_BEFORE_AT_TIME',
  ticket_sales_end_parameter: '23:00',
};

const savedQuotaSettings = {
  production_id: 'prod-1',
  quota_enabled: true,
  quota_count: 50,
  quota_buyback_enabled: true,
  quota_shortfall_unit_price: 2000,
  ticket_back_mode: 'PROGRESSIVE',
  ticket_back_conditions: [],
};

/**
 * StageArt チケット管理 (Ticket全体像整備): GET /productions/{id}/ticket-
 * sales-settings と GET /productions/{id}/quota-ticket-back-settings が
 * 追加される前は、この画面のフォームは常に空欄で開始しており、既に保存
 * 済みの販売条件を「確認できる」ことができなかった。この2つのGETの
 * レスポンスで各入力欄が初期化されることを確認する。
 */
describe('Web チケット管理: 既存の公開・販売設定が表示される', () => {
  it('seeds the sales settings and quota/ticket back form fields from the GET responses', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: baseProduction },
      { test: (u) => u.endsWith('/productions/prod-1/tickets'), status: 200, body: [] },
      { test: (u) => u.endsWith('/productions/prod-1/ticket-sales-settings'), status: 200, body: savedSalesSettings },
      { test: (u) => u.endsWith('/productions/prod-1/quota-ticket-back-settings'), status: 200, body: savedQuotaSettings },
      { test: (u) => u.endsWith('/productions/prod-1/performance-ticket-availability'), status: 200, body: [] },
    ]);

    renderRouter('src/app', { initialUrl: '/productions/prod-1/tickets' });

    await waitFor(() => expect(screen.getByTestId('production-tickets-publication-at').props.value).toBe('2026-09-01T00:00:00+09:00'));
    expect(screen.getByTestId('production-tickets-sales-start-at').props.value).toBe('2026-09-10T00:00:00+09:00');
    expect(screen.getByTestId('production-tickets-sales-end-parameter').props.value).toBe('23:00');

    await waitFor(() => expect(screen.getByTestId('production-tickets-quota-count').props.value).toBe('50'));
    expect(screen.getByTestId('production-tickets-quota-shortfall-unit-price').props.value).toBe('2000');
  });
});
