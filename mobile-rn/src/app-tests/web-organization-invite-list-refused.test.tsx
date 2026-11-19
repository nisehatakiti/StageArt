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
 * The client still believes the delegate holds メンバー管理 (e.g. the Owner
 * removed it a moment ago), but the server refuses the list: the screen
 * shows the refusal and offers no 発行 button the server would refuse too.
 */
describe('Web 参加コード: 一覧がサーバーに拒否されたとき', () => {
  it('shows the error and no issue button', async () => {
    mockFetchRoutes([
      {
        test: (u) => u.endsWith('/organizations'),
        status: 200,
        body: [{ ...orgOne, current_person_role: 'ORGANIZATION_MANAGER', current_person_management_areas: ['MEMBERS'] }],
      },
      {
        test: (u) => u.endsWith('/organizations/org-1/join-keys'),
        status: 403,
        body: { code: 'stageart_organization_access_denied', message: 'Only the Organization Owner or a delegate with member management can view Join Keys.', data: { status: 403 } },
      },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: `/organizations/${orgOne.id}/invite` });

    await waitFor(() => expect(screen.getByTestId('organization-join-key-error')).toBeVisible());
    expect(screen.getByText('この情報を表示する権限がありません。')).toBeVisible();
    expect(screen.queryByTestId('organization-join-key-issue')).toBeNull();
    expect(screen.queryByTestId('organization-join-key-active')).toBeNull();
  });
});
