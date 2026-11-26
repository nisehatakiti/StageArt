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
 * StageArt メンバー管理画面改善 §3/§4 instruction: 代理人 (a member holding
 * BOTH PARTICIPANT_MANAGER and REHEARSAL_MANAGER, mirroring
 * ProductionAuthorizationService::isProxyManager()) gains the same
 * 3-checkbox delegate-setting ability a PrimaryManager has - restricted
 * to 代理人/会計担当/受付担当 only, never the 4 PrimaryManager-only roles.
 */
describe('Web メンバー管理: 代理人による担当ロール設定', () => {
  it('lets a 代理人 (PARTICIPANT_MANAGER + REHEARSAL_MANAGER) set the 3 delegate checkboxes for another member', async () => {
    const proxyManagerProduction = { ...productionOne, is_primary_manager: false, delegate_roles: ['PARTICIPANT_MANAGER', 'REHEARSAL_MANAGER'] };
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: proxyManagerProduction },
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
          display_name: null,
          person_family_name: '鈴木',
          person_given_name: '花子',
        },
      ] },
      { test: (u) => u.endsWith('/productions/prod-1/participation-requests'), status: 200, body: [] },
      { test: (u) => u.endsWith('/productions/prod-1/delegates'), status: 200, body: [] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/productions/prod-1/participants' });

    await waitFor(() => expect(screen.getByTestId('participant-delegate-accounting-participant-1')).toBeVisible());

    (global.fetch as jest.Mock).mockImplementationOnce(async () => ({
      ok: true,
      status: 201,
      text: async () =>
        JSON.stringify({
          id: 'delegate-1',
          production_id: 'prod-1',
          person_id: 'person-9',
          person_family_name: '鈴木',
          person_given_name: '花子',
          role: 'ACCOUNTING_MANAGER',
          status: 'ACTIVE',
          created_by: 'person-1',
          created_at: '',
          updated_by: 'person-1',
          updated_at: '',
        }),
      json: async () => ({}),
    }));

    fireEvent.press(screen.getByTestId('participant-delegate-accounting-participant-1'));

    await waitFor(() => {
      const call = (global.fetch as jest.Mock).mock.calls.find(
        ([url, init]: [string, RequestInit?]) => url.endsWith('/productions/prod-1/delegates') && init?.method === 'POST'
      );
      expect(call).toBeDefined();
      const body = JSON.parse(call![1].body as string);
      expect(body.person_id).toBe('person-9');
      expect(body.role).toBe('ACCOUNTING_MANAGER');
    });
  });
});
