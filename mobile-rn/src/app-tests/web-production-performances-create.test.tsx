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

const createdPerformance = {
  id: 'perf-new',
  production_id: 'prod-1',
  performance_date: '2026-10-10',
  start_time: '13:00:00',
  end_time: null,
  capacity: 100,
  remarks: null,
  symbol: null,
  status: 'PUBLISHED',
  created_at: '',
  updated_at: '',
};

/**
 * StageArt Phase 2 Performance基盤 §23: creating a Performance from the
 * 公演スケジュール管理 screen sends POST /productions/{id}/performances with the
 * entered 公演日/開演時刻.
 */
describe('Web 公演スケジュール管理: 公演スケジュールの作成', () => {
  it('submits a new Performance via POST /productions/{id}/performances', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: baseProduction },
      { test: (u) => u.endsWith('/productions/prod-1/performances'), status: 200, body: [] },
    ]);

    renderRouter('src/app', { initialUrl: '/productions/prod-1/performances' });

    await waitFor(() => expect(screen.getByTestId('production-performances-new-date')).toBeVisible());

    fireEvent.changeText(screen.getByTestId('production-performances-new-date'), '2026-10-10');
    await waitFor(() => expect(screen.getByTestId('production-performances-new-date').props.value).toBe('2026-10-10'));

    fireEvent.changeText(screen.getByTestId('production-performances-new-start-time'), '13:00');
    await waitFor(
      () => {
        expect(screen.getByTestId('production-performances-new-start-time').props.value).toBe('13:00');
      },
      { timeout: 5000 }
    );

    (global.fetch as jest.Mock).mockImplementationOnce(async () => ({
      ok: true,
      status: 201,
      text: async () => JSON.stringify(createdPerformance),
      json: async () => createdPerformance,
    }));

    await waitFor(() => expect(screen.getByTestId('production-performances-create').props.accessibilityState?.disabled).toBe(false));

    fireEvent.press(screen.getByTestId('production-performances-create'));

    await waitFor(() => {
      const call = (global.fetch as jest.Mock).mock.calls.find(
        ([url, init]: [string, RequestInit?]) => url.endsWith('/productions/prod-1/performances') && init?.method === 'POST'
      );
      expect(call).toBeDefined();
      const body = JSON.parse(call![1].body as string);
      expect(body.performance_date).toBe('2026-10-10');
      expect(body.start_time).toBe('13:00');
    });
  });
});
