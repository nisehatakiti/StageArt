import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

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

const createdTicket = {
  id: 'ticket-new',
  production_id: 'prod-1',
  name: '学生',
  price: 3000,
  remarks: null,
  status: 'ACTIVE',
  created_at: '',
  updated_at: '',
};

/**
 * StageArt Phase 3 Ticket/Reservation基盤 §4確定事項①/§49: creating a
 * Ticket sends POST /productions/{id}/tickets with a positive integer
 * price (0円Ticketは扱わない - Domain側で強制)。
 */
describe('Web チケット管理: チケットの作成', () => {
  it('submits a new Ticket via POST /productions/{id}/tickets', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: baseProduction },
      { test: (u) => u.endsWith('/productions/prod-1/tickets'), status: 200, body: [] },
    ]);

    renderRouter('src/app', { initialUrl: '/productions/prod-1/tickets' });

    await waitFor(() => expect(screen.getByTestId('production-tickets-new-name')).toBeVisible());

    fireEvent.changeText(screen.getByTestId('production-tickets-new-name'), '学生');
    await waitFor(() => expect(screen.getByTestId('production-tickets-new-name').props.value).toBe('学生'));

    fireEvent.changeText(screen.getByTestId('production-tickets-new-price'), '3000');
    await waitFor(
      () => {
        expect(screen.getByTestId('production-tickets-new-price').props.value).toBe('3000');
      },
      { timeout: 5000 }
    );

    (global.fetch as jest.Mock).mockImplementationOnce(async () => ({
      ok: true,
      status: 201,
      text: async () => JSON.stringify(createdTicket),
      json: async () => createdTicket,
    }));

    await waitFor(() => expect(screen.getByTestId('production-tickets-add').props.accessibilityState?.disabled).toBe(false), { timeout: 5000 });

    fireEvent.press(screen.getByTestId('production-tickets-add'));

    await waitFor(() => {
      const call = (global.fetch as jest.Mock).mock.calls.find(
        ([url, init]: [string, RequestInit?]) => url.endsWith('/productions/prod-1/tickets') && init?.method === 'POST'
      );
      expect(call).toBeDefined();
      const body = JSON.parse(call![1].body as string);
      expect(body.name).toBe('学生');
      expect(body.price).toBe(3000);
    });
  });
});
