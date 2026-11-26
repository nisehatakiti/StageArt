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
  capacity: null,
  performance_common_remarks: null,
};

const project = { id: 'proj-1', organization_id: 'org-1', name: null, status: 'ACTIVE', created_at: '', updated_at: '', current_person_role: 'OWNER' };

const emptySummary = { members: [], quota_shortfall_count: 0, quota_shortfall_payable: 0 };

/**
 * StageArt 公演終了／精算処理接続 instruction: 精算 screen (this file's
 * subject) is also the confirmed "公演終了／精算処理" sidebar
 * destination, so it now also surfaces "公演を終了する（決算完了）"
 * (reusing the existing useCompleteProduction() mutation, same
 * confirmation copy as productions/[id]/index.tsx) directly from this
 * screen.
 */
describe('Web 公演終了／精算処理: 公演を終了する (ACTIVE)', () => {
  it('shows the complete-production action while ACTIVE and calls PATCH /productions/{id}/complete on confirm', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [{ id: 'org-1', name: '○○演劇団', accounting_enabled: false }] },
      { test: (u) => u.endsWith('/projects'), status: 200, body: [project] },
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: baseProduction },
      { test: (u) => u.endsWith('/productions/prod-1/settlement'), status: 200, body: emptySummary },
    ]);
    jest.spyOn(Alert, 'alert').mockImplementation((_title, _message, buttons) => {
      buttons?.find((b) => b.text === 'OK')?.onPress?.();
    });

    renderRouter('src/app', { initialUrl: '/productions/prod-1/settlement' });

    await waitFor(() => expect(screen.getByTestId('production-settlement-complete')).toBeVisible());
    expect(screen.queryByTestId('production-settlement-accounting-link')).toBeNull();

    (global.fetch as jest.Mock).mockImplementationOnce(async () => ({
      ok: true,
      status: 200,
      text: async () => JSON.stringify({ ...baseProduction, status: 'COMPLETED' }),
      json: async () => ({ ...baseProduction, status: 'COMPLETED' }),
    }));

    fireEvent.press(screen.getByTestId('production-settlement-complete'));

    await waitFor(() => {
      const call = (global.fetch as jest.Mock).mock.calls.find(
        ([url, init]: [string, RequestInit?]) => url.endsWith('/productions/prod-1/complete') && init?.method === 'PATCH'
      );
      expect(call).toBeDefined();
    });
  });
});
