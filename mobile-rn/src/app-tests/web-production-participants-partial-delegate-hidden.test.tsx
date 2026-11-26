import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty, productionOne } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * StageArt メンバー管理画面改善 §3/§9 instruction: opening this screen and
 * setting delegate roles are separate Authorizations. A member holding
 * only PARTICIPANT_MANAGER (not the full 代理人 bundle) can open the
 * screen but must not see the 3 delegate checkboxes.
 */
describe('Web メンバー管理: 代理人未満のメンバーには担当ロール欄を出さない', () => {
  it('does not offer the delegate checkboxes to a member holding only one of the two 代理人 roles', async () => {
    const partialDelegateProduction = { ...productionOne, is_primary_manager: false, delegate_roles: ['PARTICIPANT_MANAGER'] };
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: partialDelegateProduction },
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
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/productions/prod-1/participants' });

    await waitFor(() => expect(screen.getByTestId('participant-row-participant-1')).toBeVisible());
    expect(screen.queryByTestId('participant-delegate-accounting-participant-1')).toBeNull();
    expect(screen.queryByTestId('participant-delegate-proxy-participant-1')).toBeNull();
    expect(screen.queryByTestId('participant-delegate-checkin-participant-1')).toBeNull();
  });
});
