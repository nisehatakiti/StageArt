import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty, productionOne } from './__fixtures__/homeFixtures';
import { apiError, callsTo, contains, managedInvitation } from './__fixtures__/r14bFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * PR #7 R14-B stage 9 (design doc §10 / §14.2, §7.1, R14B-4R): 「招待中の
 * メンバー」 shows every invitation except accepted ones with the Backend's
 * effective_status, the send status, the resends left and the 30-day sends
 * left; at a send limit resend is disabled with the §10 text; a cancel
 * answered 409 and a resend answered 429 are shown as such.
 */
describe('R14-B メンバー管理: 招待中のメンバーの表示', () => {
  it('shows each state, send status and limit from the Backend, and the refusals', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne },
      { test: (u) => u.endsWith('/productions/prod-1/participants'), status: 200, body: [] },
      { test: (u) => u.endsWith('/productions/prod-1/participation-requests'), status: 200, body: [] },
      {
        test: (u) => u.endsWith('/productions/prod-1/participant-invitations'),
        status: 200,
        body: [
          managedInvitation({ id: 'inv-pending' }),
          managedInvitation({ id: 'inv-expired', effective_status: 'EXPIRED', is_expired: true, send_status: 'FAILED' }),
          managedInvitation({ id: 'inv-closed', effective_status: 'PRODUCTION_CLOSED', send_status: 'UNKNOWN' }),
          managedInvitation({ id: 'inv-revoked', effective_status: 'INVITER_REVOKED', send_status: 'PENDING' }),
          managedInvitation({
            id: 'inv-rejected',
            status: 'REJECTED',
            effective_status: 'REJECTED',
            reinvite_available_at: '2099-02-01T00:00:00+00:00',
          }),
          managedInvitation({ id: 'inv-cancelled', status: 'CANCELLED', effective_status: 'CANCELLED', close_reason: 'MANAGER' }),
          managedInvitation({ id: 'inv-accepted', status: 'ACCEPTED', effective_status: 'ACCEPTED' }),
          managedInvitation({
            id: 'inv-limit',
            email: 'limited@example.com',
            send_limit: { sent_in_30_days: 6, remaining_in_30_days: 0, available_at: '2099-03-01T00:00:00+00:00' },
          }),
          managedInvitation({ id: 'inv-resends', resend_count: 5, resend_remaining: 0 }),
          managedInvitation({ id: 'inv-unknown-limit', send_limit: null }),
        ],
      },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/productions/prod-1/participants' });

    await waitFor(() => expect(screen.getByTestId('participant-invitation-row-inv-pending')).toBeVisible());

    const status = (id: string) => screen.getByTestId(`participant-invitation-status-${id}`);
    expect(status('inv-pending')).toHaveTextContent(contains('承諾待ち'));
    expect(status('inv-expired')).toHaveTextContent(contains('期限切れ'));
    expect(status('inv-closed')).toHaveTextContent(contains('無効（公演終了）'));
    expect(status('inv-revoked')).toHaveTextContent(contains('無効（招待者の権限なし）'));
    expect(status('inv-rejected')).toHaveTextContent(contains('辞退'));
    expect(screen.getByTestId('participant-invitation-reinvite-inv-rejected')).toHaveTextContent(contains('以降に再び招待できます'));
    expect(status('inv-cancelled')).toHaveTextContent(contains('取消'));
    expect(screen.queryByTestId('participant-invitation-row-inv-accepted')).toBeNull();

    const send = (id: string) => screen.getByTestId(`participant-invitation-send-status-${id}`);
    expect(send('inv-pending')).toHaveTextContent(contains('送信しました（相手に届いたかどうかは確認できません）'));
    expect(send('inv-expired')).toHaveTextContent(contains('送信に失敗しました。再送してください（再送も送信回数に数えます）'));
    expect(send('inv-closed')).toHaveTextContent(contains('送信の結果を確認できませんでした。メールが届いていない場合は再送してください'));
    expect(send('inv-revoked')).toHaveTextContent(contains('送信中'));

    expect(screen.getByTestId('participant-invitation-remaining-inv-pending')).toHaveTextContent(contains('この招待の再送: あと5回'));
    expect(screen.getByTestId('participant-invitation-remaining-inv-pending')).toHaveTextContent(contains('30日間の送信: あと5回'));
    expect(screen.getByTestId('participant-invitation-remaining-inv-unknown-limit')).toHaveTextContent(contains('30日間の送信: 確認できません'));

    // Ended invitations offer no resend / cancel.
    expect(screen.queryByTestId('participant-invitation-resend-inv-rejected')).toBeNull();
    expect(screen.queryByTestId('participant-invitation-cancel-inv-cancelled')).toBeNull();

    // §10: at a send limit the resend button is disabled with the limit text.
    expect(screen.getByTestId('participant-invitation-resend-inv-limit').props.accessibilityState?.disabled).toBe(true);
    expect(screen.getByTestId('participant-invitation-limit-inv-limit')).toHaveTextContent(contains('このメールアドレスへの送信は30日間で6回までです。'));
    expect(screen.getByTestId('participant-invitation-limit-inv-limit')).toHaveTextContent(contains('以降に送信できます。'));
    expect(screen.getByTestId('participant-invitation-resend-inv-resends').props.accessibilityState?.disabled).toBe(true);
    expect(screen.getByTestId('participant-invitation-limit-inv-resends')).toHaveTextContent(contains('この招待の再送は5回までです。取り消して新しく招待しても、30日間の送信回数は数え直されません。'));

    // §10: the create button is disabled for an address at its 30-day limit.
    fireEvent.changeText(screen.getByTestId('production-participants-new-member-name'), '制限 太郎');
    fireEvent.changeText(screen.getByTestId('production-participants-new-member-email'), ' Limited@Example.com ');
    await waitFor(() => expect(screen.getByTestId('production-participants-add-member-blocked')).toHaveTextContent(contains('30日間で6回まで')));
    expect(screen.getByTestId('production-participants-add-member').props.accessibilityState?.disabled).toBe(true);

    // A cancel answered 409 (already answered / closed) is shown as such - not as a 422.
    (global.fetch as jest.Mock).mockImplementationOnce(apiError(409, 'stageart_participant_invitation_not_pending'));
    fireEvent.press(screen.getByTestId('participant-invitation-cancel-inv-pending'));
    await waitFor(() =>
      expect(screen.getByTestId('production-participants-error')).toHaveTextContent(contains('この招待は既に承諾・辞退・取消などで処理済みのため、この操作はできません。'))
    );
    expect(callsTo('/participant-invitations/inv-pending/cancel', 'POST')).toHaveLength(1);

    // §9.2: a resend over the rate limit shows how long to wait.
    (global.fetch as jest.Mock).mockImplementationOnce(apiError(429, 'stageart_rate_limited', { retry_after: 1225 }));
    fireEvent.press(screen.getByTestId('participant-invitation-resend-inv-pending'));
    await waitFor(() =>
      expect(screen.getByTestId('production-participants-error')).toHaveTextContent(contains('操作の回数が上限に達しました。約21分後にもう一度お試しください。'))
    );
  });
});
