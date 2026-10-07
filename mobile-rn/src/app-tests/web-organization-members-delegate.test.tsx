import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty, orgOne } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * StageArt 団体管理UI整理 "代理人を設定": Membership::changeRole() toward
 * RoleKey::ORGANIZATION_MANAGER via the real
 * POST /organizations/{id}/delegates (AssignOrganizationDelegateUseCase) -
 * no separate Delegate Entity, no 準備中 placeholder.
 */
describe('Web 団体管理: メンバー管理 > 代理人を設定', () => {
  it('lists non-owner members and assigns a delegate via the real endpoint', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [orgOne] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
      {
        test: (u) => u.endsWith(`/organizations/${orgOne.id}/members`),
        status: 200,
        body: [
          { membership_id: 'm-1', person_id: 'person-1', person_family_name: '舞台', person_given_name: '芸術', role_key: 'OWNER', joined_at: '2026-01-01T00:00:00+09:00' },
          { membership_id: 'm-2', person_id: 'person-2', person_family_name: '山田', person_given_name: '太郎', role_key: 'MEMBER', joined_at: '2026-01-02T00:00:00+09:00' },
        ],
      },
      { test: (u) => u.endsWith(`/organizations/${orgOne.id}/delegates`), status: 204, body: null },
    ]);

    renderRouter('src/app', { initialUrl: `/organizations/${orgOne.id}/members/delegate` });

    await waitFor(() => expect(screen.getByTestId('organization-delegate-row-person-2')).toBeVisible());
    expect(screen.queryByTestId('organization-delegate-row-person-1')).toBeNull();

    fireEvent.press(screen.getByTestId('organization-delegate-assign-person-2'));

    await waitFor(() => {
      const postCall = (global.fetch as jest.Mock).mock.calls.find(
        ([url, options]: [string, RequestInit]) => url.includes(`/organizations/${orgOne.id}/delegates`) && options?.method === 'POST'
      );
      expect(postCall).toBeDefined();
      expect(JSON.parse(postCall[1].body as string)).toMatchObject({ person_id: 'person-2' });
    });
  });
});
