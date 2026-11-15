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

const checkInResult = {
  check_in_id: 'checkin-1',
  reservation_id: 'reservation-1',
  reservation_number: 'ABCD234567',
  performance_id: 'perf-1',
  status: 'COMPLETED',
  reservation_status: 'CHECKED_IN',
  checked_in_by: 'person-1',
  checked_in_at: '',
  already_processed: false,
};

/**
 * StageArt 予約→発券→受付Check-in一連接続: 予約番号でのCheck-in
 * (CheckIn.md「QR Check InとManual Selectionは同じCheck In Factとして扱う」-
 * QRコードの内容は予約番号そのものなので、これは実機のQRスキャナーが将来
 * 実装されても同じエンドポイントに帰着する、既に接続済みの受付経路)。
 */
describe('Web 受付: 予約番号（QRコードの内容）による受付', () => {
  it('submits the typed/scanned reservation number to POST /performances/{id}/checkin/by-number', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: baseProduction },
      { test: (u) => u.endsWith('/productions/prod-1/performances'), status: 200, body: [performance] },
      { test: (u) => u.endsWith('/productions/prod-1/tickets'), status: 200, body: [] },
      { test: (u) => u.endsWith('/productions/prod-1/participants'), status: 200, body: [] },
      { test: (u) => u.includes('/performances/perf-1/checkin/reservations') && !u.includes('walk-up'), status: 200, body: [] },
    ]);

    renderRouter('src/app', { initialUrl: '/productions/prod-1/checkin' });

    await waitFor(() => expect(screen.getByTestId('production-checkin-number-entry')).toBeVisible());

    fireEvent.changeText(screen.getByTestId('production-checkin-number-entry'), 'ABCD234567');
    await waitFor(() => expect(screen.getByTestId('production-checkin-number-submit').props.accessibilityState?.disabled).toBe(false));

    (global.fetch as jest.Mock).mockImplementationOnce(async () => ({
      ok: true,
      status: 200,
      text: async () => JSON.stringify(checkInResult),
      json: async () => checkInResult,
    }));

    fireEvent.press(screen.getByTestId('production-checkin-number-submit'));

    await waitFor(() => {
      const call = (global.fetch as jest.Mock).mock.calls.find(([url]: [string]) => url.includes('/checkin/by-number'));
      expect(call).toBeDefined();
      const body = JSON.parse(call![1].body as string);
      expect(body.reservation_number).toBe('ABCD234567');
    });

    await waitFor(() => expect(screen.getByTestId('production-checkin-message')).toBeVisible());
  });
});
