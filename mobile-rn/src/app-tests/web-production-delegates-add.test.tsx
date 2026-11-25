import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty, productionOne } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * ProductionDelegate実用化 instruction §6/§14/§17: 追加 - picking an
 * existing Production Participant candidate (subject_type PERSON, the
 * only source available without a Person search API - see delegates.tsx's
 * own docblock), picking a Role, and submitting sends
 * POST /productions/{id}/delegates with that person_id and role.
 *
 * PR #7 R14-B (design doc §4.6 / §10; expectations added for the spec
 * change): only an ACTIVE PERSON member whose consent is confirmed is a
 * candidate (an unconfirmed member is not), and there is no Person ID
 * input any more.
 */
describe('Web 担当者: 追加', () => {
  it('adds a delegate by picking a Participant candidate and a Role', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne },
      { test: (u) => u.endsWith('/productions/prod-1/delegates'), status: 200, body: [] },
      { test: (u) => u.endsWith('/productions/prod-1/participants'), status: 200, body: [
        {
          id: 'participant-1',
          production_id: 'prod-1',
          subject_type: 'PERSON',
          subject_id: 'person-9',
          participant_type: 'STAFF',
          status: 'ACTIVE',
          created_at: '',
          updated_at: '',
          remarks: null,
          display_name: '担当 一郎',
          person_family_name: null,
          person_given_name: null,
          email: null,
          enrollment_source: 'INVITATION',
          consent_status: 'CONFIRMED',
          consented_at: '2026-11-01T00:00:00+00:00',
        },
        {
          id: 'participant-2',
          production_id: 'prod-1',
          subject_type: 'PERSON',
          subject_id: 'person-8',
          participant_type: 'CAST',
          status: 'ACTIVE',
          created_at: '',
          updated_at: '',
          remarks: null,
          display_name: '旧方式 次郎',
          person_family_name: null,
          person_given_name: null,
          email: null,
          enrollment_source: null,
          consent_status: 'UNCONFIRMED',
          consented_at: null,
        },
      ] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/productions/prod-1/delegates' });

    await waitFor(() => expect(screen.getByTestId('production-delegates-candidate-person-9')).toBeVisible());
    expect(screen.getByText('担当 一郎（スタッフ）')).toBeVisible();
    expect(screen.queryByTestId('production-delegates-candidate-person-8')).toBeNull();
    expect(screen.queryByTestId('production-delegates-new-person-id')).toBeNull();

    fireEvent.press(screen.getByTestId('production-delegates-candidate-person-9'));
    fireEvent.press(screen.getByTestId('production-delegates-new-role-QUESTIONNAIRE_MANAGER'));

    await waitFor(() => expect(screen.getByTestId('production-delegates-add-submit').props.accessibilityState?.disabled).toBe(false));

    (global.fetch as jest.Mock).mockImplementationOnce(async () => ({
      ok: true,
      status: 201,
      text: async () =>
        JSON.stringify({
          id: 'delegate-1',
          production_id: 'prod-1',
          person_id: 'person-9',
          person_family_name: null,
          person_given_name: null,
          role: 'QUESTIONNAIRE_MANAGER',
          status: 'ACTIVE',
          created_by: 'person-1',
          created_at: '',
          updated_by: 'person-1',
          updated_at: '',
        }),
      json: async () => ({}),
    }));

    fireEvent.press(screen.getByTestId('production-delegates-add-submit'));

    await waitFor(() => {
      const call = (global.fetch as jest.Mock).mock.calls.find(
        ([url, init]: [string, RequestInit?]) => url.endsWith('/productions/prod-1/delegates') && init?.method === 'POST'
      );
      expect(call).toBeDefined();
      const body = JSON.parse(call![1].body as string);
      expect(body.person_id).toBe('person-9');
      expect(body.role).toBe('QUESTIONNAIRE_MANAGER');
    });
  });
});
