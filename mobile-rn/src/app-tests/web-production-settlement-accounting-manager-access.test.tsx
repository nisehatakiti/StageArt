import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

const accountingManagerProduction = {
  id: 'prod-1',
  project_id: 'proj-1',
  name: '第10回公演',
  title_heading: null,
  status: 'ACTIVE',
  slug: 'prod-1-slug',
  published_at: null,
  primary_manager_person_id: 'person-owner',
  created_at: '',
  updated_at: '',
  is_primary_manager: false,
  delegate_role: 'ACCOUNTING_MANAGER',
  delegate_roles: ['ACCOUNTING_MANAGER'],
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
  capacity: null,
  performance_common_remarks: null,
};

const summaryWithOneMember = {
  members: [
    {
      person_id: 'person-2',
      display_name: '山田太郎',
      confirmed_ticket_back_amount: 300,
      already_settled_amount: 0,
      outstanding_amount: 300,
      last_settled_amount: 0,
    },
  ],
  quota_shortfall_count: 0,
  quota_shortfall_payable: 0,
};

/**
 * StageArt 担当者権限をメンバー管理へ統合・整理 instruction §会計担当仕様訂正:
 * ACCOUNTING_MANAGER now covers Settlement too (Backend: RolePermissions
 * grants Settlement.Manage) - this screen's own frontend gate
 * (previously `is_primary_manager` only, the exact "UI側に別のPrimaryManager
 * のみ判定が残っている" case this instruction asked to check for) must
 * recognize delegate_roles including ACCOUNTING_MANAGER too, or a real
 * ACCOUNTING_MANAGER delegate would be blocked from a screen the Backend
 * already allows them to use.
 */
describe('Web 公演終了／精算処理: 会計担当(ACCOUNTING_MANAGER)によるアクセス', () => {
  it('lets a non-PrimaryManager ACCOUNTING_MANAGER delegate view and settle members, but hides the 公演終了（決算完了）action', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [] },
      { test: (u) => u.endsWith('/projects'), status: 200, body: [] },
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: accountingManagerProduction },
      { test: (u) => u.endsWith('/productions/prod-1/settlement'), status: 200, body: summaryWithOneMember },
    ]);

    renderRouter('src/app', { initialUrl: '/productions/prod-1/settlement' });

    await waitFor(() => expect(screen.queryByTestId('production-settlement-forbidden')).toBeNull());
    await waitFor(() => expect(screen.getByTestId('settlement-checkbox-person-2')).toBeVisible());

    // Settlement Lifecycle Action (公演終了・決算完了) stays PrimaryManager-only -
    // Settlement.Manage does not extend to it.
    expect(screen.queryByTestId('production-settlement-complete')).toBeNull();

    (global.fetch as jest.Mock).mockImplementationOnce(async () => ({
      ok: true,
      status: 200,
      text: async () => JSON.stringify({ status: 'ok' }),
      json: async () => ({ status: 'ok' }),
    }));

    fireEvent.press(screen.getByTestId('settlement-checkbox-person-2'));

    await waitFor(() => {
      const call = (global.fetch as jest.Mock).mock.calls.find(
        ([url, init]: [string, RequestInit?]) => url.endsWith('/productions/prod-1/settlement/members/person-2/settle') && init?.method === 'POST'
      );
      expect(call).toBeDefined();
    });
  });
});
