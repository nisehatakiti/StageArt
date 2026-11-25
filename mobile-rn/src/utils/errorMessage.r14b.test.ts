import { ApiError, NetworkError } from '@/api/errors';

import { getErrorMessage, retryAfterSeconds } from './errorMessage';

const error = (status: number, code: string, data: Record<string, unknown> = {}) => new ApiError(status, code, code, { status, ...data });

/**
 * PR #7 R14-B stage 9: the participant / invitation / delegate refusals by
 * code (the status code as the Backend sends it), with the §10 texts where
 * the design gives them; other errors keep their existing messages.
 */
describe('getErrorMessage: R14-B codes', () => {
  it('shows retry_after for 429 stageart_rate_limited', () => {
    expect(getErrorMessage(error(429, 'stageart_rate_limited', { retry_after: 30 }))).toBe('操作の回数が上限に達しました。30秒後にもう一度お試しください。');
    expect(getErrorMessage(error(429, 'stageart_rate_limited', { retry_after: 600 }))).toBe('操作の回数が上限に達しました。約10分後にもう一度お試しください。');
    expect(getErrorMessage(error(429, 'stageart_rate_limited', { retry_after: 3599 }))).toBe('操作の回数が上限に達しました。約60分後にもう一度お試しください。');
    expect(getErrorMessage(error(429, 'stageart_rate_limited'))).toBe('操作の回数が上限に達しました。しばらくしてからもう一度お試しください。');
    expect(retryAfterSeconds(error(429, 'stageart_rate_limited', { retry_after: 2.2 }))).toBe(3);
    expect(retryAfterSeconds(error(409, 'stageart_participant_invitation_not_pending'))).toBeNull();
  });

  it('maps the R14-B codes', () => {
    const cases: [ApiError, string][] = [
      [error(500, 'stageart_email_key_not_configured'), 'サーバーの設定が不足しています。'],
      [error(429, 'stageart_participant_invitation_resend_limit'), 'この招待の再送は5回までです。取り消して新しく招待しても、30日間の送信回数は数え直されません。'],
      [error(409, 'stageart_participant_withdraw_primary_manager'), '公演の管理者（PrimaryManager）は参加をやめられません。先に管理者を引き継いでください。'],
      [error(409, 'stageart_participant_withdraw_not_allowed'), '現在参加中ではないため、参加をやめる操作はできません。'],
      [error(409, 'stageart_participant_type_conflict'), 'この人は、変更先の区分で既にこの公演に登録されています。'],
      [error(409, 'stageart_participant_draft_exists'), 'この公演での参加の情報に確認が必要なため、承諾できません。公演の管理者に問い合わせてください。'],
      [error(410, 'stageart_participant_invitation_expired'), 'この招待は有効期限が切れています。'],
      [error(404, 'stageart_participant_invitation_not_found'), 'この招待は見つかりません。招待一覧から確認してください。'],
      [error(422, 'stageart_production_delegate_target_not_consented'), 'この方は同意が確認できていないため、担当者にできません。メンバー管理から「同意の確認を依頼」してください。'],
    ];
    for (const [apiError, text] of cases) {
      expect(getErrorMessage(apiError)).toBe(text);
    }
  });

  it('reads not_pending the same for the cancel 409 and the resend 422', () => {
    const text = 'この招待は既に承諾・辞退・取消などで処理済みのため、この操作はできません。';
    expect(getErrorMessage(error(409, 'stageart_participant_invitation_not_pending'))).toBe(text);
    expect(getErrorMessage(error(422, 'stageart_participant_invitation_not_pending'))).toBe(text);
  });

  it('puts available_at into the send-limit text', () => {
    expect(getErrorMessage(error(429, 'stageart_participant_invitation_send_limit', { available_at: '2099-03-01T00:00:00+00:00' }))).toMatch(
      /^このメールアドレスへの送信は30日間で6回までです。.+以降に送信できます。$/
    );
  });

  it('keeps the existing messages for other errors', () => {
    expect(getErrorMessage(new ApiError(401, 'x'))).toBe('認証が切れました。再度ログインしてください。');
    expect(getErrorMessage(new ApiError(403, 'x', 'stageart_participant_access_denied'))).toBe('この情報を表示する権限がありません。');
    expect(getErrorMessage(new ApiError(404, 'x'))).toBe('情報が見つかりませんでした。');
    expect(getErrorMessage(new ApiError(500, 'x', 'stageart_internal_error'))).toBe('読み込みに失敗しました。(500)');
    expect(getErrorMessage(new NetworkError(new Error('x')))).toBe('サーバーへ接続できませんでした。通信環境を確認してください。');
  });
});
