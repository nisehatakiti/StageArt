import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty, orgOne } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * StageArt 団体管理UI整理 "メンバー管理": GET /organizations/{id}/members
 * now exists (ListOrganizationMembersUseCase) - this screen shows the
 * real roster for an Owner/代理人, not the "準備中" placeholder the
 * earlier Backend gap forced (see git history for that earlier state).
 */
describe('Web メンバー管理', () => {
  it('shows the current Person’s own Role and the real member roster', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [orgOne] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
      {
        test: (u) => u.endsWith('/organizations/org-1/members'),
        status: 200,
        body: [
          { membership_id: 'm-1', person_id: 'person-1', person_family_name: '舞台', person_given_name: '芸術', role_key: 'OWNER', joined_at: '2026-01-01T00:00:00+09:00' },
          { membership_id: 'm-2', person_id: 'person-2', person_family_name: '山田', person_given_name: '太郎', role_key: 'MEMBER', joined_at: '2026-01-02T00:00:00+09:00' },
        ],
      },
    ]);

    renderRouter('src/app', { initialUrl: '/organizations/org-1/members' });

    await waitFor(() => expect(screen.getByTestId('organization-members-self-role')).toBeVisible());
    expect(screen.getByText('オーナー（代表者）')).toBeVisible();

    await waitFor(() => expect(screen.getByTestId('organization-members-list')).toBeVisible());
    expect(screen.getByTestId('organization-member-person-2')).toHaveTextContent(/山田/);
    expect(screen.getByTestId('organization-members-add')).toBeVisible();
    expect(screen.getByTestId('organization-members-delegate')).toBeVisible();
    expect(screen.getByTestId('organization-members-owner-transfer')).toBeVisible();
  });
});
