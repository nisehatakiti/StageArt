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

const existingQuestionnaire = {
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

const createdQuestion = {
  id: 'question-1',
  questionnaire_id: 'questionnaire-1',
  text: '公演はいかがでしたか？',
  type: 'SINGLE_CHOICE',
  required: true,
  display_order: 0,
  choices: [
    { id: 'choice-1', label: '良い', display_order: 0 },
    { id: 'choice-2', label: '普通', display_order: 1 },
  ],
  created_at: '',
  updated_at: '',
};

/**
 * アンケート実装指示書 §6/§7/§30: adding a SINGLE_CHOICE Question sends
 * POST /productions/{id}/questionnaire/questions with the entered text,
 * type, required flag, and choice labels.
 */
describe('Web アンケート管理: 質問の追加', () => {
  it('adds a SINGLE_CHOICE question with two choices', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1') && !u.includes('questionnaire'), status: 200, body: baseProduction },
      { test: (u) => u.endsWith('/productions/prod-1/questionnaire'), status: 200, body: existingQuestionnaire },
    ]);

    renderRouter('src/app', { initialUrl: '/productions/prod-1/questionnaire/question' });

    await waitFor(() => expect(screen.getByTestId('questionnaire-question-text')).toBeVisible());

    fireEvent.changeText(screen.getByTestId('questionnaire-question-text'), '公演はいかがでしたか？');

    // SINGLE_CHOICE is the default type; confirm it explicitly for
    // clarity/robustness against a future default change.
    fireEvent.press(screen.getByTestId('questionnaire-question-type-SINGLE_CHOICE'));
    fireEvent.press(screen.getByTestId('questionnaire-question-required-toggle'));

    fireEvent.changeText(screen.getByTestId('questionnaire-question-choice-0'), '良い');
    fireEvent.changeText(screen.getByTestId('questionnaire-question-choice-1'), '普通');
    await waitFor(() => expect(screen.getByTestId('questionnaire-question-submit').props.accessibilityState?.disabled).toBe(false));

    (global.fetch as jest.Mock).mockImplementationOnce(async () => ({
      ok: true,
      status: 201,
      text: async () => JSON.stringify(createdQuestion),
      json: async () => createdQuestion,
    }));

    fireEvent.press(screen.getByTestId('questionnaire-question-submit'));

    await waitFor(() => {
      const call = (global.fetch as jest.Mock).mock.calls.find(
        ([url, init]: [string, RequestInit?]) => url.endsWith('/productions/prod-1/questionnaire/questions') && init?.method === 'POST'
      );
      expect(call).toBeDefined();
      const body = JSON.parse(call![1].body as string);
      expect(body.text).toBe('公演はいかがでしたか？');
      expect(body.type).toBe('SINGLE_CHOICE');
      expect(body.required).toBe(true);
      expect(body.choices).toEqual(['良い', '普通']);
    });
  });
});
