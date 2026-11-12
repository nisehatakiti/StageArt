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

const ticketA = {
  id: 'ticket-1',
  production_id: 'prod-1',
  name: '一般',
  price: 5000,
  remarks: null,
  status: 'ACTIVE',
  created_at: '',
  updated_at: '',
};

/**
 * StageArt Phase 3 Ticket/Reservation基盤 §49: the チケット管理 screen -
 * confirms the Ticket list renders from GET /productions/{id}/tickets.
 * Kept as its own single-test file (not combined with create/forbidden
 * cases) matching this codebase's established convention against multi-
 * `it()`/multi-`renderRouter()` files, which corrupt the RTL `screen`
 * singleton across tests within the same file.
 */
describe('Web チケット管理: 一覧表示', () => {
  it('shows the Ticket list for a Primary Manager', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: baseProduction },
      { test: (u) => u.endsWith('/productions/prod-1/tickets'), status: 200, body: [ticketA] },
    ]);

    renderRouter('src/app', { initialUrl: '/productions/prod-1/tickets' });

    await waitFor(() => expect(screen.getByTestId('ticket-row-ticket-1')).toBeVisible());

    expect(screen.getByText('一般')).toBeVisible();
    expect(screen.getByText('5000円')).toBeVisible();
  });
});
