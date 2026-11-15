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
  venue_name: '○○ホール',
  venue_published_at: '2026-01-01T00:00:00+09:00',
  schedule_start_date: '2026-10-10',
  schedule_end_date: '2026-10-12',
  schedule_published_at: '2026-01-01T00:00:00+09:00',
  script_credit: '山田太郎',
  direction_credit: '鈴木花子',
  script_direction_published_at: '2026-01-01T00:00:00+09:00',
};

/**
 * StageArt Phase 1 (docs/12-FunctionalStructure.md §20): the five
 * Production Information sections newly added to the single Production
 * Information screen. Pre-fills every section from an already-saved
 * Production, edits only the one section without existing content
 * (description), and confirms the save request carries the newly-typed
 * description plus every already-saved section's value unchanged.
 */
describe('Web 公演情報編集: 公演説明・会場・日程・脚本演出セクション', () => {
  it('pre-fills every information section and sends the new description alongside the unchanged ones on save', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: baseProduction },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/productions/prod-1/edit' });

    await waitFor(() => expect(screen.getByTestId('production-edit-venue').props.value).toBe('○○ホール'));
    expect(screen.getByTestId('production-edit-schedule-start').props.value).toBe('2026-10-10');
    expect(screen.getByTestId('production-edit-schedule-end').props.value).toBe('2026-10-12');
    expect(screen.getByTestId('production-edit-script-credit').props.value).toBe('山田太郎');
    expect(screen.getByTestId('production-edit-direction-credit').props.value).toBe('鈴木花子');

    fireEvent.changeText(screen.getByTestId('production-edit-description'), 'あらすじ本文');
    await waitFor(() => expect(screen.getByTestId('production-edit-description').props.value).toBe('あらすじ本文'));

    fireEvent.press(screen.getByTestId('production-edit-submit'));

    await waitFor(() => {
      const call = (global.fetch as jest.Mock).mock.calls.find(
        ([url, init]: [string, RequestInit?]) => url.endsWith('/productions/prod-1') && init?.method === 'PUT'
      );
      expect(call).toBeDefined();
      const body = JSON.parse(call![1].body as string);
      expect(body.description).toBe('あらすじ本文');
      expect(body.description_published_at).toEqual(expect.any(String));
      expect(body.venue_name).toBe('○○ホール');
      expect(body.venue_published_at).toBe('2026-01-01T00:00:00+09:00');
      expect(body.schedule_start_date).toBe('2026-10-10');
      expect(body.schedule_end_date).toBe('2026-10-12');
      expect(body.script_credit).toBe('山田太郎');
      expect(body.direction_credit).toBe('鈴木花子');
    });
  });
});
