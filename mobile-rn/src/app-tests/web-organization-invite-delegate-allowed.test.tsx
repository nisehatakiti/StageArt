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
 * A delegate holding メンバー管理 manages the 参加コード like the Owner:
 * the active code is listed and disabling it calls the real endpoint.
 */
describe('Web 参加コード: メンバー管理を持つ代理人', () => {
  it('sees the active code and can disable it', async () => {
    mockFetchRoutes([
      {
        test: (u) => u.endsWith('/organizations'),
        status: 200,
        body: [{ ...orgOne, current_person_role: 'ORGANIZATION_MANAGER', current_person_management_areas: ['MEMBERS'] }],
      },
      {
        test: (u) => u.endsWith('/organizations/org-1/join-keys'),
        status: 200,
        body: [{ id: 'key-1', code: 'ABCD1234', target_type: 'ORGANIZATION', target_id: 'org-1', status: 'ACTIVE', expires_at: null, max_uses: null, use_count: 2 }],
      },
      {
        test: (u) => u.endsWith('/join-keys/key-1/disable'),
        status: 200,
        body: { id: 'key-1', code: 'ABCD1234', target_type: 'ORGANIZATION', target_id: 'org-1', status: 'DISABLED', expires_at: null, max_uses: null, use_count: 2 },
      },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: `/organizations/${orgOne.id}/invite` });

    await waitFor(() => expect(screen.getByTestId('organization-join-key-active')).toBeVisible());
    expect(screen.getByText('ABCD1234')).toBeVisible();
    expect(screen.queryByTestId('organization-invite-unavailable')).toBeNull();

    fireEvent.press(screen.getByTestId('organization-join-key-disable'));

    await waitFor(() => {
      const disableCall = (global.fetch as jest.Mock).mock.calls.find(
        ([url, options]: [string, RequestInit]) => String(url).endsWith('/join-keys/key-1/disable') && options?.method === 'POST'
      );
      expect(disableCall).toBeDefined();
    });
  });
});
