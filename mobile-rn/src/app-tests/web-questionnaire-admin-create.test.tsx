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

const createdQuestionnaire = {
  id: 'questionnaire-1',
  production_id: 'prod-1',
  title: '公演アンケート',
  description: null,
  status: 'DRAFT',
  response_end_at: null,
  created_at: '',
  updated_at: '',
  public_url: 'https://dummy.stageart.top/theatre-co/prod-1-slug/questionnaire',
  questions: [],
};

/**
 * アンケート実装指示書 §31/AC-01/AC-03: creating a Production's first
 * Questionnaire (none exists yet - GET returns 404) sends
 * POST /productions/{id}/questionnaire with the entered title.
 */
describe('Web アンケート管理: 作成', () => {
  it('creates a Questionnaire via POST /productions/{id}/questionnaire when none exists yet', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1') && !u.includes('questionnaire'), status: 200, body: baseProduction },
      { test: (u) => u.endsWith('/productions/prod-1/questionnaire'), status: 404, body: { message: 'Questionnaire not found' } },
    ]);

    renderRouter('src/app', { initialUrl: '/productions/prod-1/questionnaire' });

    await waitFor(() => expect(screen.getByTestId('questionnaire-create-title')).toBeVisible());

    fireEvent.changeText(screen.getByTestId('questionnaire-create-title'), '公演アンケート');
    await waitFor(() => expect(screen.getByTestId('questionnaire-create-submit').props.accessibilityState?.disabled).toBe(false));

    (global.fetch as jest.Mock).mockImplementationOnce(async () => ({
      ok: true,
      status: 201,
      text: async () => JSON.stringify(createdQuestionnaire),
      json: async () => createdQuestionnaire,
    }));

    fireEvent.press(screen.getByTestId('questionnaire-create-submit'));

    await waitFor(() => {
      const call = (global.fetch as jest.Mock).mock.calls.find(
        ([url, init]: [string, RequestInit?]) => url.endsWith('/productions/prod-1/questionnaire') && init?.method === 'POST'
      );
      expect(call).toBeDefined();
      const body = JSON.parse(call![1].body as string);
      expect(body.title).toBe('公演アンケート');
    });
  });
});
