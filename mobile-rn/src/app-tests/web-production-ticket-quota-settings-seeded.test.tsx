import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty } from './__fixtures__/homeFixtures';

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
 * StageArt チケット管理の整理: チケットノルマ／チケットバック設定は専用
 * メニュー (productions/[id]/quota.tsx) に分離された(旧
 * web-production-tickets-sales-settings-seeded.test.tsx が tickets.tsx
 * 側でカバーしていたシード挙動のうち、ノルマ側をこの画面で引き続き確認す
 * る - テストを削除せず移設)。
 */
describe('Web チケットノルマ: 既存のノルマ・チケットバック設定が表示される', () => {
  it('seeds quotaCount/quotaShortfallUnitPrice from the GET quota-ticket-back-settings response', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: baseProduction },
      { test: (u) => u.endsWith('/productions/prod-1/quota-ticket-back-settings'), status: 200, body: savedQuotaSettings },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/productions/prod-1/quota' });

    await waitFor(() => expect(screen.getByTestId('production-ticket-quota-count').props.value).toBe('50'));
    expect(screen.getByTestId('production-ticket-quota-shortfall-unit-price').props.value).toBe('2000');
  });
});
