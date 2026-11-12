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
  capacity: null,
  performance_common_remarks: null,
};

const project = { id: 'proj-1', organization_id: 'org-1', name: null, status: 'ACTIVE', created_at: '', updated_at: '', current_person_role: 'OWNER' };

const emptySummary = { members: [], quota_shortfall_count: 0, quota_shortfall_payable: 0 };

/**
 * StageArt 公演終了／精算処理接続 instruction: "必要な会計処理" links to
 * the existing Production Accounting summary screen, gated by
 * Organization Accounting being enabled - same condition useNavMenu.ts
 * already uses for Organization Context's own 会計管理 entry, not a new
 * permission rule.
 */
describe('Web 公演終了／精算処理: 会計へのリンク', () => {
  it('shows the accounting link when the Organization has Accounting enabled', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [{ id: 'org-1', name: '○○演劇団', accounting_enabled: true }] },
      { test: (u) => u.endsWith('/projects'), status: 200, body: [project] },
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: baseProduction },
      { test: (u) => u.endsWith('/productions/prod-1/settlement'), status: 200, body: emptySummary },
    ]);

    renderRouter('src/app', { initialUrl: '/productions/prod-1/settlement' });

    await waitFor(() => expect(screen.getByTestId('production-settlement-accounting-link')).toBeVisible());
  });
});
