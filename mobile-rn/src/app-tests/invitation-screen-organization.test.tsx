import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * Invitation policy 2026-11-27: the organization invitation mail opens
 * /organization-invitations/{id} - the same rule as a Production invitation
 * (GET /me/organization-invitations/{id}; nothing to accept or reject).
 */
describe('招待の画面（団体）', () => {
  it('shows the organization invitation, its state and the way to the organization', async () => {
    mockFetchRoutes([
      {
        test: (u) => u.endsWith('/me/organization-invitations/org-invitation-1'),
        status: 200,
        body: { id: 'org-invitation-1', organization_id: 'org-1', organization_name: '劇団テスト', status: 'REGISTERED' },
      },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    const router = renderRouter('src/app', { initialUrl: '/organization-invitations/org-invitation-1' });

    await waitFor(() => expect(screen.getByTestId('organization-invitation-details')).toBeVisible());
    expect(screen.getByText('劇団テスト')).toBeVisible();
    expect(screen.getByTestId('organization-invitation-status-REGISTERED')).toHaveTextContent('この団体のメンバーとして登録されています。');
    expect(screen.queryByText(/承諾|辞退|参加する/)).toBeNull();

    fireEvent.press(screen.getByTestId('organization-invitation-open-organization'));
    await waitFor(() => expect(router.getPathname()).toBe('/organizations/org-1'));
  });
});
