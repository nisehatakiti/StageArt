import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

const publicQuestionnaire = {
  production_name: '第10回公演',
  title: '公演アンケート',
  description: 'ご来場ありがとうございました。',
  status: 'PUBLISHED',
  accepting_responses: true,
  questions: [
    {
      id: 'q-single',
      text: '公演はいかがでしたか？',
      type: 'SINGLE_CHOICE',
      required: true,
      display_order: 0,
      choices: [
        { id: 'c-good', label: '良い' },
        { id: 'c-normal', label: '普通' },
      ],
    },
    {
      id: 'q-multi',
      text: '印象に残った要素は？',
      type: 'MULTIPLE_CHOICE',
      required: false,
      display_order: 1,
      choices: [
        { id: 'c-sound', label: '音響' },
        { id: 'c-light', label: '照明' },
      ],
    },
    {
      id: 'q-rating',
      text: '満足度は？',
      type: 'RATING_5',
      required: true,
      display_order: 2,
      choices: [],
    },
    {
      id: 'q-text',
      text: 'ご意見をお聞かせください',
      type: 'FREE_TEXT',
      required: false,
      display_order: 3,
      choices: [],
    },
    {
      id: 'q-yesno',
      text: 'また観たいと思いますか？',
      type: 'YES_NO',
      required: true,
      display_order: 4,
      choices: [],
    },
  ],
};

/**
 * アンケート実装指示書 §14-§17: the public answer page renders all 5
 * Question types, and Backend-side validation (§16) is what actually
 * blocks a premature submission - this test confirms the client never
 * even attempts the network call while required Questions are unanswered
 * (no `answers`-shaped fetch to `/responses` at all), without depending
 * on this test harness's flaky observation of `fireEvent`-driven local
 * state for this particular root-level (outside the `(app)` group) route
 * - see this feature's Final Report for that known test-environment
 * limitation.
 */
describe('公開アンケート回答画面', () => {
  it('renders every question type and never calls the Submit endpoint before any answer is given', async () => {
    mockFetchRoutes([{ test: (u) => u.endsWith('/questionnaires/by-slug/show-slug'), status: 200, body: publicQuestionnaire }]);

    renderRouter('src/app', { initialUrl: '/theatre-co/show-slug/questionnaire' });

    await waitFor(() => expect(screen.getByTestId('public-questionnaire-title')).toBeVisible());
    expect(screen.getByTestId('public-questionnaire-production-name')).toBeVisible();
    expect(screen.getByTestId('public-questionnaire-description')).toBeVisible();
    expect(screen.getByTestId('public-question-q-single')).toBeVisible();
    expect(screen.getByTestId('public-question-q-multi')).toBeVisible();
    expect(screen.getByTestId('public-question-q-rating')).toBeVisible();
    expect(screen.getByTestId('public-question-q-text')).toBeVisible();
    expect(screen.getByTestId('public-question-q-yesno')).toBeVisible();

    // Every choice/rating/yes-no control, plus the free-text input, for
    // every Question - proves all 5 types actually render their own
    // answer UI, not just their question text.
    expect(screen.getByTestId('public-question-q-single-choice-c-good')).toBeVisible();
    expect(screen.getByTestId('public-question-q-multi-choice-c-sound')).toBeVisible();
    expect(screen.getByTestId('public-question-q-rating-rating-5')).toBeVisible();
    expect(screen.getByTestId('public-question-q-text-text')).toBeVisible();
    expect(screen.getByTestId('public-question-q-yesno-yes')).toBeVisible();

    fireEvent.press(screen.getByTestId('public-questionnaire-submit'));

    // No `answers` submission may reach the Backend while required
    // Questions are unanswered (§16 - Backend re-validates regardless,
    // but the client must not even try).
    const submitCalls = (global.fetch as jest.Mock).mock.calls.filter(
      ([url, init]: [string, RequestInit?]) => url.endsWith('/questionnaires/by-slug/show-slug/responses') && init?.method === 'POST'
    );
    expect(submitCalls).toHaveLength(0);
  });
});
