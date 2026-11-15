import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

const checkInDelegateProduction = {
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
  delegate_role: 'CHECKIN_MANAGER',
  delegate_roles: ['CHECKIN_MANAGER'],
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

/**
 * StageArt 担当者権限をメンバー管理へ統合・整理 instruction §会計担当仕様訂正:
 * companion to web-production-settlement-accounting-manager-access.test.tsx -
 * confirms the frontend's relaxed gate (PrimaryManager OR ACCOUNTING_MANAGER)
 * still correctly excludes a delegate with a different Role (CHECKIN_MANAGER),
 * matching the Backend's own RolePermissions scoping.
 */
describe('Web 公演終了／精算処理: ACCOUNTING_MANAGER以外のDelegateは引き続き拒否', () => {
  it('shows the forbidden message for a CHECKIN_MANAGER delegate', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [] },
      { test: (u) => u.endsWith('/projects'), status: 200, body: [] },
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: checkInDelegateProduction },
      { test: (u) => u.endsWith('/productions/prod-1/settlement'), status: 403, body: { code: 'stageart_settlement_access_denied', message: 'forbidden' } },
    ]);

    renderRouter('src/app', { initialUrl: '/productions/prod-1/settlement' });

    await waitFor(() => expect(screen.getByTestId('production-settlement-forbidden')).toBeVisible());
  });
});
