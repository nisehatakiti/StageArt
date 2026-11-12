import { Alert } from 'react-native';
import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty, orgOne, projectOne } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

const planningProduction = {
  id: 'prod-1',
  project_id: 'proj-1',
  name: '○○公演2026',
  title_heading: null,
  status: 'PLANNING',
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

/**
 * StageArt Production Lifecycle整理 instruction: "「公演を確定する」という
 * 明確な処理を実行できるようにしてください" - a PLANNING Production shows a
 * "公演を確定する" Action (not the removed DRAFT-only "企画を開始する"
 * button), and confirming it sends PATCH /productions/{id}/activate. This
 * screen's confirmAlert() resolves to Alert.alert in this Jest environment
 * (see web-production-checkin-walkup-first-stage.test.tsx's own docblock
 * for the same established pattern) - spy on it and invoke the confirming
 * button's onPress to simulate the user tapping OK.
 */
describe('Web 公演管理トップ: 公演を確定する', () => {
  it('shows the confirm Action for a PLANNING Production and PATCHes /activate on confirmation', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [orgOne] },
      { test: (u) => u.endsWith('/projects'), status: 200, body: [projectOne] },
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: planningProduction },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);
    const alertSpy = jest.spyOn(Alert, 'alert').mockImplementation(() => undefined);

    renderRouter('src/app', { initialUrl: '/productions/prod-1' });

    await waitFor(() => expect(screen.getByTestId('production-management-activate')).toBeVisible());
    expect(screen.queryByTestId('production-management-start-planning')).toBeNull();

    (global.fetch as jest.Mock).mockImplementationOnce(async () => ({
      ok: true,
      status: 200,
      text: async () => JSON.stringify({ ...planningProduction, status: 'ACTIVE', published_at: '2026-09-21T00:00:00+09:00' }),
      json: async () => ({ ...planningProduction, status: 'ACTIVE', published_at: '2026-09-21T00:00:00+09:00' }),
    }));

    fireEvent.press(screen.getByTestId('production-management-activate'));

    expect(alertSpy).toHaveBeenCalledTimes(1);
    const [title, message, buttons] = alertSpy.mock.calls[0];
    expect(title).toBe('公演を確定');
    expect(message).toContain('公開');

    const confirmButton = buttons?.find((button) => button.text === 'OK');
    confirmButton?.onPress?.();

    await waitFor(() => {
      const call = (global.fetch as jest.Mock).mock.calls.find(
        ([url, init]: [string, RequestInit?]) => url.endsWith('/productions/prod-1/activate') && init?.method === 'PATCH'
      );
      expect(call).toBeDefined();
    });
  });
});
