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
 * PR #7 R14-B (design doc §7.1 D10, §10, §14.2; rewritten for the spec
 * change - previously this checked that an address of an existing account
 * added the Person directly): an address that already belongs to a StageArt
 * account is invited like any other - the same single POST, answered
 * INVITATION_SENT, and the screen says the person becomes a member when
 * they accept. 「メンバーを追加しました」 is never shown.
 */
describe('Web メンバー管理: 既存アカウントのメールアドレスも招待になる', () => {
  it('invites an address that belongs to an existing account, without adding anyone', async () => {
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
          outcome: 'INVITATION_SENT',
          invitation: {
            id: 'invitation-1',
            production_id: 'prod-1',
            email: 'already-registered@example.com',
            name: '山田 太郎',
            invited_by_person_id: 'person-1',
            participant_type: 'CAST',
            remarks: null,
            status: 'PENDING',
            created_at: '',
            expires_at: '2099-01-01T00:00:00+00:00',
            consumed_at: null,
            is_expired: false,
          },
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

    await waitFor(() => expect(screen.getByText('招待メールを送信しました。本人が承諾するとメンバーに追加されます。')).toBeVisible());
    expect(screen.queryByText('メンバーを追加しました。')).toBeNull();
    expect(screen.getByText('＋ メンバーを招待')).toBeVisible();
  });
});
