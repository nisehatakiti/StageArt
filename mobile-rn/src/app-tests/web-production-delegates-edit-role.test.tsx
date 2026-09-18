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
 * ProductionDelegate実用化 instruction §8/§17: 編集 - tapping a different
 * Role on an existing delegate row sends PUT /production-delegates/{id}
 * with the new role and the delegate's current status (the endpoint
 * requires both fields together, not a partial update).
 */
describe('Web 担当者: Role変更', () => {
  it('changes an existing delegate role via PUT /production-delegates/{id}', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne },
      { test: (u) => u.endsWith('/productions/prod-1/delegates'), status: 200, body: [
        {
          id: 'delegate-1',
          production_id: 'prod-1',
          person_id: 'person-9',
          person_family_name: '山田',
          person_given_name: '太郎',
          role: 'PARTICIPANT_MANAGER',
          status: 'ACTIVE',
          created_by: 'person-1',
          created_at: '',
          updated_by: 'person-1',
          updated_at: '',
        },
      ] },
      { test: (u) => u.endsWith('/productions/prod-1/participants'), status: 200, body: [] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/productions/prod-1/delegates' });

    await waitFor(() => expect(screen.getByTestId('production-delegate-role-delegate-1-QUESTIONNAIRE_MANAGER')).toBeVisible());

    (global.fetch as jest.Mock).mockImplementationOnce(async () => ({
      ok: true,
      status: 200,
      text: async () =>
        JSON.stringify({
          id: 'delegate-1',
          production_id: 'prod-1',
          person_id: 'person-9',
          person_family_name: '山田',
          person_given_name: '太郎',
          role: 'QUESTIONNAIRE_MANAGER',
          status: 'ACTIVE',
          created_by: 'person-1',
          created_at: '',
          updated_by: 'person-1',
          updated_at: '',
        }),
      json: async () => ({}),
    }));

    fireEvent.press(screen.getByTestId('production-delegate-role-delegate-1-QUESTIONNAIRE_MANAGER'));

    await waitFor(() => {
      const call = (global.fetch as jest.Mock).mock.calls.find(
        ([url, init]: [string, RequestInit?]) => url.endsWith('/production-delegates/delegate-1') && init?.method === 'PUT'
      );
      expect(call).toBeDefined();
      const body = JSON.parse(call![1].body as string);
      expect(body.role).toBe('QUESTIONNAIRE_MANAGER');
      expect(body.status).toBe('ACTIVE');
    });
  });
});
