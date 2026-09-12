import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty } from './__fixtures__/homeFixtures';

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
};

/**
 * StageArt Phase 1 (docs/21-MemberManagementScreen.md): the rebuilt
 * メンバー管理 screen - confirms a name-only member (no StageArt account)
 * can be staged locally and is only sent to the server when [更新] is
 * pressed, via POST /productions/{id}/participants with
 * subject_type=NAME_ONLY.
 */
describe('Web メンバー管理: 氏名のみでのメンバー追加と一括更新', () => {
  it('stages a new name-only member locally and sends it on 更新', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: baseProduction },
      { test: (u) => u.endsWith('/productions/prod-1/participants'), status: 200, body: [] },
      { test: (u) => u.endsWith('/productions/prod-1/participation-requests'), status: 200, body: [] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/productions/prod-1/participants' });

    await waitFor(() => expect(screen.getByTestId('production-participants-new-name')).toBeVisible());

    fireEvent.changeText(screen.getByTestId('production-participants-new-name'), '山田太郎');
    await waitFor(() => expect(screen.getByTestId('production-participants-new-name').props.value).toBe('山田太郎'));

    fireEvent.press(screen.getByTestId('production-participants-add'));
    await waitFor(() => expect(screen.getByTestId('production-participants-pending-0')).toBeVisible(), { timeout: 3000 });

    // Not sent yet - staged only, matching §21.9's single-[更新]-button save.
    expect((global.fetch as jest.Mock).mock.calls.some(([url, init]: [string, RequestInit?]) => url.endsWith('/participants') && init?.method === 'POST')).toBe(
      false
    );

    fireEvent.press(screen.getByTestId('production-participants-save'));

    await waitFor(() => {
      const call = (global.fetch as jest.Mock).mock.calls.find(
        ([url, init]: [string, RequestInit?]) => url.endsWith('/productions/prod-1/participants') && init?.method === 'POST'
      );
      expect(call).toBeDefined();
      const body = JSON.parse(call![1].body as string);
      expect(body.subject_type).toBe('NAME_ONLY');
      expect(body.display_name).toBe('山田太郎');
      expect(body.participant_type).toBe('CAST');
    });
  });
});
