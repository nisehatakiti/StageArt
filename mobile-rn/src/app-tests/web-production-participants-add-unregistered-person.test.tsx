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
 * StageArt Productionメンバー追加(氏名＋メールアドレス統一)ラウンド §2-B/§19:
 * submitting 氏名＋メールアドレス for an email with no existing StageArt
 * Person creates a ParticipantInvitation carrying the entered name -
 * never a NAME_ONLY Participant.
 *
 * Invitation policy 2026-11-27 (expectation updated for the spec change):
 * the answer is INVITATION_SENT and the result text says the person becomes
 * a member once the invited address is registered and verified (no
 * acceptance).
 */
describe('Web メンバー管理: 未登録メールアドレスへの登録案内送信', () => {
  it('sends a registration-guidance invitation when the email has no existing Person', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne },
      { test: (u) => u.endsWith('/productions/prod-1/participants'), status: 200, body: [] },
      { test: (u) => u.endsWith('/productions/prod-1/participation-requests'), status: 200, body: [] },
      { test: (u) => u.endsWith('/productions/prod-1/participant-invitations'), status: 200, body: [] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/productions/prod-1/participants' });

    await waitFor(() => expect(screen.getByTestId('production-participants-new-member-name')).toBeVisible());

    fireEvent.changeText(screen.getByTestId('production-participants-new-member-name'), '山田 花子');
    fireEvent.changeText(screen.getByTestId('production-participants-new-member-email'), 'unknown@example.com');
    await waitFor(() => expect(screen.getByTestId('production-participants-new-member-email').props.value).toBe('unknown@example.com'));

    (global.fetch as jest.Mock).mockImplementationOnce(async () => ({
      ok: true,
      status: 201,
      text: async () =>
        JSON.stringify({
          outcome: 'INVITATION_SENT',
          invitation: {
            id: 'invitation-1',
            production_id: 'prod-1',
            email: 'unknown@example.com',
            name: '山田 花子',
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
      expect(body.name).toBe('山田 花子');
      expect(body.email).toBe('unknown@example.com');
    });

    await waitFor(() =>
      expect(screen.getByText('招待メールを送信しました。招待したメールアドレスで登録とメールアドレスの確認が済むと、メンバーに追加されます。')).toBeVisible()
    );
  });
});
