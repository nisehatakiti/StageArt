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

const performance = {
  id: 'perf-1',
  production_id: 'prod-1',
  performance_date: '2026-10-01',
  start_time: '18:00',
  end_time: null,
  capacity: 100,
  remarks: null,
  symbol: null,
  status: 'ACTIVE',
  created_at: '',
  updated_at: '',
};

const reservedReservation = {
  id: 'reservation-1',
  reservation_number: 'ABCD234567',
  performance_id: 'perf-1',
  ticket_id: 'ticket-1',
  booker_name: 'メンバー手売り太郎',
  booker_email: 'member-sold@example.com',
  guest_count: 1,
  price_snapshot: 3000,
  status: 'RESERVED',
  attributed_person_id: null,
  created_by: null,
  created_at: '',
  updated_by: null,
  updated_at: '',
};

/**
 * StageArt 予約→発券→受付Check-in一連接続: メンバー手売り等で来場しなかっ
 * た予約をNO_SHOWとして記録する受付操作（CheckInProcessor::processNoShow()
 * -既にこの回で会計連携込みで実装済みのUseCaseの、受付UI側からの接続確認）。
 */
describe('Web 受付: 不参加（NO_SHOW）の記録', () => {
  it('marks a searched RESERVED reservation as no-show via POST .../no-show', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: baseProduction },
      { test: (u) => u.endsWith('/productions/prod-1/performances'), status: 200, body: [performance] },
      { test: (u) => u.endsWith('/productions/prod-1/tickets'), status: 200, body: [] },
      { test: (u) => u.endsWith('/productions/prod-1/participants'), status: 200, body: [] },
      {
        test: (u) => u.includes('/performances/perf-1/checkin/reservations') && !u.includes('walk-up'),
        status: 200,
        body: [reservedReservation],
      },
    ]);

    renderRouter('src/app', { initialUrl: '/productions/prod-1/checkin' });

    await waitFor(() => expect(screen.getByTestId(`checkin-action-noshow-${reservedReservation.id}`)).toBeVisible());

    (global.fetch as jest.Mock).mockImplementationOnce(async () => ({
      ok: true,
      status: 200,
      text: async () => '',
      json: async () => null,
    }));

    fireEvent.press(screen.getByTestId(`checkin-action-noshow-${reservedReservation.id}`));

    await waitFor(() => {
      const call = (global.fetch as jest.Mock).mock.calls.find(([url]: [string]) => url.includes(`/checkin/reservations/${reservedReservation.id}/no-show`));
      expect(call).toBeDefined();
    });

    await waitFor(() => expect(screen.getByTestId('production-checkin-message')).toBeVisible());
  });
});
