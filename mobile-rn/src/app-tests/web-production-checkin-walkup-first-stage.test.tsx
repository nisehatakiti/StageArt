import { Alert } from 'react-native';
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

const ticket = {
  id: 'ticket-1',
  production_id: 'prod-1',
  name: '一般',
  price: 3000,
  remarks: null,
  status: 'ACTIVE',
  created_at: '',
  updated_at: '',
};

/**
 * Phase 0-4統合監査 P1-3: 当日券登録は「入力→OK→確認ポップアップ→OK→登録」
 * の2段階操作にする。この画面のconfirmAlert()はこのJest環境ではNative半分
 * (`@/utils/confirmAlert` -> Alert.alert委譲)に解決される
 * (confirmAlert-web.test.tsx自身のdocblock、NativeDrawerMenu.test.tsxの
 * 既存パターンを踏襲)ため、Alert.alertをspyして確認する。単一テスト/ファイル
 * の既存慣例(§24)に合わせ、3つのシナリオを3ファイルに分割。
 */
describe('Web 受付: 当日券の2段階確認登録（第1段階）', () => {
  it('shows a confirmation dialog with guest count and attribution, and does not register yet', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: baseProduction },
      { test: (u) => u.endsWith('/productions/prod-1/performances'), status: 200, body: [performance] },
      { test: (u) => u.endsWith('/productions/prod-1/tickets'), status: 200, body: [ticket] },
      { test: (u) => u.endsWith('/productions/prod-1/participants'), status: 200, body: [] },
      { test: (u) => u.includes('/performances/perf-1/checkin/reservations') && !u.includes('walk-up'), status: 200, body: [] },
    ]);
    const alertSpy = jest.spyOn(Alert, 'alert').mockImplementation(() => undefined);

    renderRouter('src/app', { initialUrl: '/productions/prod-1/checkin' });

    await waitFor(() => expect(screen.getByTestId(`walkup-ticket-${ticket.id}`)).toBeVisible());
    fireEvent.press(screen.getByTestId(`walkup-ticket-${ticket.id}`));
    fireEvent.changeText(screen.getByTestId('walkup-booker-name'), '当日券太郎');
    fireEvent.changeText(screen.getByTestId('walkup-booker-email'), 'walkup@example.com');
    await waitFor(() => expect(screen.getByTestId('production-checkin-walkup-submit').props.accessibilityState?.disabled).toBe(false));

    fireEvent.press(screen.getByTestId('production-checkin-walkup-submit'));

    expect(alertSpy).toHaveBeenCalledTimes(1);
    const [title, message] = alertSpy.mock.calls[0];
    expect(title).toBe('当日券の登録確認');
    expect(message).toContain('1名');
    expect(message).toContain('未設定');

    const walkUpCalls = (global.fetch as jest.Mock).mock.calls.filter(([url]: [string]) => url.includes('/checkin/walk-up'));
    expect(walkUpCalls).toHaveLength(0);
  });
});
