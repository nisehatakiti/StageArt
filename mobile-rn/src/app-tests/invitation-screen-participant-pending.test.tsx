import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty } from './__fixtures__/homeFixtures';
import { myInvitation } from './__fixtures__/r14bFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * Invitation policy (docs/03-InvitationAndEnrollmentPolicy.md, 2026-11-27):
 * the Production invitation mail opens /participant-invitations/{id}; the
 * screen asks GET /me/participant-invitations/{id} for that one invitation
 * and shows its state. Nobody accepts or rejects: there is no 「参加する」 /
 * 「参加しない」, and opening it sends nothing.
 */
describe('招待の画面（公演）: 登録待ち', () => {
  it('shows the invitation and its state without any accept / reject', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/me/participant-invitations/invitation-1'), status: 200, body: myInvitation() },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/participant-invitations/invitation-1' });

    await waitFor(() => expect(screen.getByTestId('participant-invitation-details')).toBeVisible());
    expect(screen.getByText('踊れチュパカブラ')).toBeVisible();
    expect(screen.getByText('役割: 出演者')).toBeVisible();
    expect(screen.getByTestId('participant-invitation-status-PENDING')).toHaveTextContent(
      '招待されたメールアドレスの確認が完了すると、この公演の参加者として登録されます。'
    );
    expect(screen.queryByText('参加する')).toBeNull();
    expect(screen.queryByText('参加しない')).toBeNull();
    expect(screen.queryByText(/承諾|辞退/)).toBeNull();
    expect(screen.queryByTestId('participant-invitation-open-production')).toBeNull();

    const calls = (global.fetch as jest.Mock).mock.calls as [string, RequestInit?][];
    expect(calls.filter(([url, init]) => String(url).includes('participant-invitations') && (init?.method ?? 'GET') !== 'GET')).toEqual([]);
  });
});
