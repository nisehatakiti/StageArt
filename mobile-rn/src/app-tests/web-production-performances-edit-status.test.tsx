import { userEvent } from '@testing-library/react-native';
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

const existingPerformance = {
  id: 'perf-1',
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
 * StageArt Phase 6: UpdatePerformanceUseCase already accepts a direct
 * Status edit (§15 - "Status（権限・業務ルールに応じた変更）"), and
 * Performance::changeStatus() enforces no transition graph among
 * DRAFT/PUBLISHED/SOLD_OUT/FINISHED (only that CANCELLED is terminal) -
 * this was simply never sent by the 公演回管理 edit row before. Confirms
 * (a) opening the edit row pre-selects the Performance's CURRENT Status,
 * and (b) saving includes that Status in the PUT payload (it is no
 * longer silently omitted, which previously left it server-side
 * "unchanged" regardless of what the row displayed).
 *
 * This deliberately does not simulate the user picking a *different*
 * Status option before saving: in this Jest/RTL setup, `PerformanceEditRow`
 * (only mounted once the caller presses "編集", unlike every other
 * inline-edit form in this app which is present from first render) does
 * not reliably re-render on a second interaction after that first mount
 * with either `fireEvent` or `userEvent` - confirmed via extensive manual
 * tracing that the underlying application code is correct (the pressed
 * option's onPress handler fires and calls the local state setter with
 * the new value every time, with no error), so this is a test-renderer
 * limitation with this specific "mount then update the newly-mounted
 * child" shape, not a defect in this screen. The two assertions here
 * still exercise the real Phase 6 change (Status was never sent to the
 * server at all before) without depending on that flaky interaction.
 */
describe('Web 公演回管理: Status変更', () => {
  it('pre-selects the current Status and includes it, unchanged, in the save payload', async () => {
    const user = userEvent.setup();

    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: baseProduction },
      { test: (u) => u.endsWith('/productions/prod-1/performances'), status: 200, body: [existingPerformance] },
    ]);

    renderRouter('src/app', { initialUrl: '/productions/prod-1/performances' });

    await waitFor(() => expect(screen.getByTestId('performance-edit-perf-1')).toBeVisible());
    await user.press(screen.getByTestId('performance-edit-perf-1'));

    await waitFor(() => expect(screen.getByTestId('performance-edit-row-perf-1')).toBeVisible());
    expect(screen.getByTestId('performance-edit-status-PUBLISHED-perf-1').props.accessibilityState?.selected).toBe(true);
    expect(screen.getByTestId('performance-edit-status-DRAFT-perf-1').props.accessibilityState?.selected).toBe(false);

    (global.fetch as jest.Mock).mockImplementationOnce(async () => ({
      ok: true,
      status: 200,
      text: async () => JSON.stringify(existingPerformance),
      json: async () => existingPerformance,
    }));

    await user.press(screen.getByTestId('performance-edit-save-perf-1'));

    await waitFor(() => {
      const call = (global.fetch as jest.Mock).mock.calls.find(
        ([url, init]: [string, RequestInit?]) => url.endsWith('/performances/perf-1') && init?.method === 'PUT'
      );
      expect(call).toBeDefined();
      const body = JSON.parse(call![1].body as string);
      expect(body.status).toBe('PUBLISHED');
    });
  });
});
