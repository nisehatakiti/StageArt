import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty } from './__fixtures__/homeFixtures';
import { apiAnswer, apiError, myInvitation } from './__fixtures__/r14bFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * PR #7 R14-B stage 9 (design doc §7.2 / §10 / §14.2): the answer of
 * accept is shown as the API says - 404 (not theirs / missing), 410
 * (expired), 409 (already answered), 409 draft_exists (§10 text), 429 (with
 * retry_after) and 403 - and the invitation stays on screen to try again.
 */
describe('R14-B 招待確認: 承諾の失敗', () => {
  it('shows each refusal from the API', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/me/participant-invitations'), status: 200, body: [myInvitation()] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
      { test: (u) => u.endsWith('/me/participating-productions'), status: 200, body: [] },
      { test: (u) => u.endsWith('/productions'), status: 200, body: [] },
    ]);

    renderRouter('src/app', { initialUrl: '/participant-invitations/invitation-1' });
    await waitFor(() => expect(screen.getByTestId('participant-invitation-accept')).toBeVisible());

    const cases: [() => Promise<unknown>, string][] = [
      [apiError(404, 'stageart_participant_invitation_not_found'), 'この招待は見つかりません。招待一覧から確認してください。'],
      [apiError(410, 'stageart_participant_invitation_expired'), 'この招待は有効期限が切れています。'],
      [apiError(409, 'stageart_participant_invitation_not_pending'), 'この招待は既に承諾・辞退・取消などで処理済みのため、この操作はできません。'],
      [apiError(409, 'stageart_participant_draft_exists'), 'この公演での参加の情報に確認が必要なため、承諾できません。公演の管理者に問い合わせてください。'],
      [apiError(409, 'stageart_participant_invitation_closed', { close_reason: 'PRODUCTION_CLOSED' }), 'この招待は現在承諾できません（公演の終了、または招待した管理者の権限がなくなったため）。'],
      [apiError(429, 'stageart_rate_limited', { retry_after: 45 }), '操作の回数が上限に達しました。45秒後にもう一度お試しください。'],
      [apiError(403, 'stageart_participant_invitation_forbidden'), 'このアカウントでは招待に回答できません。メールアドレスの確認が済んでいるかご確認ください。'],
    ];

    for (const [answer, text] of cases) {
      (global.fetch as jest.Mock).mockImplementationOnce(answer);
      fireEvent.press(screen.getByTestId('participant-invitation-accept'));
      await waitFor(() => expect(screen.getByTestId('participant-invitation-error')).toHaveTextContent(text));
      expect(screen.getByTestId('participant-invitation-details')).toBeVisible();
    }

    // Declining works after the refusals.
    (global.fetch as jest.Mock).mockImplementationOnce(apiAnswer(200, { id: 'invitation-1', status: 'REJECTED', close_reason: null }));
    fireEvent.press(screen.getByTestId('participant-invitation-reject'));
    await waitFor(() => expect(screen.getByTestId('participant-invitation-rejected')).toHaveTextContent('招待を辞退しました。'));
  });
});
