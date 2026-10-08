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

const savedSalesSettings = {
  production_id: 'prod-1',
  ticket_publication_at: '2026-09-01T00:00:00+09:00',
  ticket_sales_start_at: '2026-09-10T00:00:00+09:00',
  ticket_sales_end_rule: 'DAY_BEFORE_AT_TIME',
  ticket_sales_end_parameter: '23:00',
};

/**
 * StageArt チケット管理の整理: チケット公開日時・販売開始日時・販売終了
 * ルールは公演情報画面 (productions/[id]/edit.tsx) が正式な設定場所になった
 * (旧 web-production-tickets-sales-settings-seeded.test.tsx が
 * tickets.tsx側でカバーしていたシード挙動を、移動先のこの画面で引き続き
 * 確認する - テストを削除せず移設)。datetime-localの値はローカル時刻表示
 * (formatDateTimeLocal()) になるため、入力のJST +09:00と一致する
 * "YYYY-MM-DDTHH:MM" を期待する。
 */
describe('Web 公演情報編集: チケット公開・販売設定が既存値からシードされる', () => {
  it('seeds publicationAt/salesStartAt/salesEndParameter from the GET ticket-sales-settings response', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: baseProduction },
      { test: (u) => u.endsWith('/productions/prod-1/ticket-sales-settings'), status: 200, body: savedSalesSettings },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/productions/prod-1/edit' });

    await waitFor(() => expect(screen.getByTestId('production-edit-ticket-publication-at').props.value).toBe('2026-09-01T00:00'));
    expect(screen.getByTestId('production-edit-ticket-sales-start-at').props.value).toBe('2026-09-10T00:00');
    expect(screen.getByTestId('production-edit-ticket-sales-end-time').props.value).toBe('23:00');
    expect(screen.getByTestId('production-edit-ticket-publication-at').props.step).toBe(300);
    expect(screen.getByTestId('production-edit-ticket-sales-start-at').props.step).toBe(300);
  });
});
