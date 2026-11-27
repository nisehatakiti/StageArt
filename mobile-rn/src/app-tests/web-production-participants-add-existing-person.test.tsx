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
 * Invitation policy (docs/03-InvitationAndEnrollmentPolicy.md, 2026-11-27;
 * rewritten for the spec change - R14-B invited such an address and waited
 * for an acceptance): an address that belongs to a verified StageArt account
 * is registered at once - the same single POST, answered MEMBER_ADDED, and
 * the screen says 「メンバーに追加しました。」 and refetches the members.
 */
describe('Web メンバー管理: 確認済みの既存アカウントはその場でメンバーになる', () => {
  it('adds a verified existing account at once with the same single POST', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne },
      { test: (u) => u.endsWith('/productions/prod-1/participants'), status: 200, body: [] },
      { test: (u) => u.endsWith('/productions/prod-1/participation-requests'), status: 200, body: [] },
      { test: (u) => u.endsWith('/productions/prod-1/participant-invitations'), status: 200, body: [] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/productions/prod-1/participants' });

    await waitFor(() => expect(screen.getByTestId('production-participants-new-member-name')).toBeVisible());

    fireEvent.changeText(screen.getByTestId('production-participants-new-member-name'), '山田 太郎');
    fireEvent.changeText(screen.getByTestId('production-participants-new-member-email'), 'already-registered@example.com');
    await waitFor(() => expect(screen.getByTestId('production-participants-new-member-email').props.value).toBe('already-registered@example.com'));

    (global.fetch as jest.Mock).mockImplementationOnce(async () => ({
      ok: true,
      status: 201,
      text: async () =>
        JSON.stringify({
          outcome: 'MEMBER_ADDED',
          participant_id: 'participant-9',
        }),
      json: async () => ({}),
    }));

    fireEvent.press(screen.getByTestId('production-participants-add-member'));

    await waitFor(() => {
      const call = (global.fetch as jest.Mock).mock.calls.find(
        ([url, init]: [string, RequestInit?]) => url.endsWith('/productions/prod-1/participant-invitations') && init?.method === 'POST'
      );
      expect(call).toBeDefined();
      const body = JSON.parse(call![1].body as string);
      expect(body.name).toBe('山田 太郎');
      expect(body.email).toBe('already-registered@example.com');
      expect(body.participant_type).toBe('CAST');
    });

    await waitFor(() => expect(screen.getByText('メンバーに追加しました。')).toBeVisible());
    expect(screen.queryByText(/承諾/)).toBeNull();
    expect(screen.getByText('＋ メンバーを招待')).toBeVisible();
    await waitFor(() =>
      expect(
        (global.fetch as jest.Mock).mock.calls.filter(([url, init]: [string, RequestInit?]) => url.endsWith('/productions/prod-1/participants') && (init?.method ?? 'GET') === 'GET').length
      ).toBeGreaterThan(1)
    );
  });
});
