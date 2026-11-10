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

/** A member settled 300 for one seat whose Check-in was refunded afterwards. */
const overpaidSummary = {
  members: [
    {
      person_id: 'person-2',
      display_name: '架空 メンバー',
      confirmed_ticket_back_amount: 0,
      already_settled_amount: 300,
      outstanding_amount: 0,
      overpaid_amount: 300,
      last_settled_amount: 300,
    },
  ],
  quota_shortfall_count: 0,
  quota_shortfall_payable: 0,
};

/**
 * PR #7 review (R7): no over-payment line while settled equals confirmed.
 * Its own file: one renderRouter() per file (see home-single-org.test.tsx).
 */
describe('Web 精算: 払い過ぎがない場合', () => {
  it('shows no over-payment line while settled equals confirmed', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [{ id: 'org-1', name: '○○演劇団', accounting_enabled: false }] },
      { test: (u) => u.endsWith('/projects'), status: 200, body: [project] },
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: baseProduction },
      {
        test: (u) => u.endsWith('/productions/prod-1/settlement'),
        status: 200,
        body: { ...overpaidSummary, members: [{ ...overpaidSummary.members[0], confirmed_ticket_back_amount: 300, overpaid_amount: 0 }] },
      },
    ]);

    renderRouter('src/app', { initialUrl: '/productions/prod-1/settlement' });

    await waitFor(() => expect(screen.getByTestId('settlement-row-person-2')).toBeVisible());
    expect(screen.queryByTestId('settlement-overpaid-person-2')).toBeNull();
  });
});
