import { ApiError, NetworkError } from '@/api/errors';

/** PR #7 R12: the 409 code of POST /productions/{id}/rehearsals for an
 * idempotency key reused with different content. */
export const REHEARSAL_IDEMPOTENCY_CONFLICT = 'stageart_rehearsal_idempotency_conflict';

/** True for that 409 (and nothing else - not other 409s, not network errors). */
export function isRehearsalIdempotencyConflict(error: unknown): boolean {
  return error instanceof ApiError && error.statusCode === 409 && error.code === REHEARSAL_IDEMPOTENCY_CONFLICT;
}

/**
 * Maps API/Network errors to a Japanese message, distinguishing
 * Authentication (401) from Authorization (403) from Not Found (404)
 * from Network Error, per Phase 5.2 §17/§24's explicit requirement that
 * these never be blurred together in the UI.
 */
export function getErrorMessage(error: unknown): string {
  if (error instanceof NetworkError) {
    return 'サーバーへ接続できませんでした。通信環境を確認してください。';
  }

  if (error instanceof ApiError) {
    // PR #7 review (R5): the server's own answers for a Production that
    // no longer allows the operation (409) and for a busy database that
    // rolled the request back (503). Nothing is resent automatically -
    // mutations have no retry - so the user is told nothing was saved
    // and decides to try again.
    if (error.code === 'stageart_production_closed') {
      return 'この公演は中止または決算完了済みのため、この操作はできません。';
    }
    if (error.code === 'stageart_database_busy') {
      return 'サーバーが混み合っていたため、保存されませんでした。少し待ってからもう一度お試しください。';
    }
    // PR #7 R12 follow-up (docs/20 §26.4): a Rehearsal create whose
    // idempotency key was already used for different content. Only the
    // create endpoint answers this code; other 409s keep their own text.
    if (error.code === REHEARSAL_IDEMPOTENCY_CONFLICT) {
      return '同じ送信キーで異なる内容の稽古を登録しようとしました。画面を再読み込みして、もう一度登録してください。';
    }
    // PR #7 review (R6): a capacity below the seats already reserved.
    // PR #7 R14-A (F1): the Performance's status refuses the operation -
    // a new reservation / walk-up sale / increase on a Performance that is
    // not on sale (満席・中止・終了), or a Check-in on a cancelled one.
    if (error.code === 'stageart_performance_not_accepting') {
      const status = error.data?.performance_status;
      if (status === 'CANCELLED') {
        return 'この公演回は中止されているため、この操作はできません。';
      }
      if (status === 'SOLD_OUT') {
        return 'この公演回は満席のため、新しい予約・当日券・人数の追加はできません。';
      }
      if (status === 'FINISHED') {
        return 'この公演回は終了しているため、新しい予約・当日券・人数の追加はできません。';
      }
      return 'この公演回は現在、この操作を受け付けていません。';
    }
    if (error.code === 'stageart_capacity_below_reservations') {
      return '予約済みの席数より少ない定員には変更できません。定員は変更されていません。';
    }
    // PR #7 review (R7): a member was settled more than is now confirmed
    // (a Check-in refunded after the settlement).
    if (error.code === 'stageart_production_settlement_overpaid') {
      const total = error.data?.total_overpaid_amount;
      const amount = typeof total === 'number' ? `（合計${total.toLocaleString('ja-JP')}円）` : '';
      return `精算済みの額が確定したチケットバックを上回っているメンバーがいるため${amount}、決算を完了できません。精算画面で該当メンバーの精算を取り消し、確定額で精算し直してください。`;
    }
    const r14b = r14bMessage(error);
    if (r14b !== null) {
      return r14b;
    }
    if (error.statusCode === 401) {
      return '認証が切れました。再度ログインしてください。';
    }
    if (error.statusCode === 403) {
      return 'この情報を表示する権限がありません。';
    }
    if (error.statusCode === 404) {
      return '情報が見つかりませんでした。';
    }
    return `読み込みに失敗しました。(${error.statusCode})`;
  }

  return '読み込みに失敗しました。';
}

/** A server date-time (ISO 8601) as the user's local date and time, or null when absent / unreadable. */
export function formatServerDateTime(value: unknown): string | null {
  if (typeof value !== 'string' || value === '') {
    return null;
  }
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? null : date.toLocaleString('ja-JP');
}

/**
 * PR #7 R14-B (design doc §4-§10): `retry_after` of a 429
 * stageart_rate_limited, in whole seconds, or null.
 */
export function retryAfterSeconds(error: unknown): number | null {
  if (!(error instanceof ApiError) || error.code !== 'stageart_rate_limited') {
    return null;
  }
  const value = error.data?.retry_after;
  return typeof value === 'number' && value > 0 ? Math.ceil(value) : null;
}

function waitText(seconds: number): string {
  if (seconds < 60) {
    return `${seconds}秒`;
  }
  return `約${Math.ceil(seconds / 60)}分`;
}

/**
 * PR #7 R14-B: the participant / invitation / delegate refusals the
 * Backend answers by code (status codes as the Backend sends them - e.g.
 * not_pending is 409 for cancel and 422 for resend; both read the same).
 * Texts given in the design doc §10 are used verbatim. Null for any other
 * error, which keeps its existing message.
 */
function r14bMessage(error: ApiError): string | null {
  switch (error.code) {
    // §9.2: 429 with retry_after (seconds to the end of the window).
    case 'stageart_rate_limited': {
      const seconds = retryAfterSeconds(error);
      return seconds !== null
        ? `操作の回数が上限に達しました。${waitText(seconds)}後にもう一度お試しください。`
        : '操作の回数が上限に達しました。しばらくしてからもう一度お試しください。';
    }
    // §6.7: the managers are only told the server is not set up.
    case 'stageart_email_key_not_configured':
      return 'サーバーの設定が不足しています。';
    // §10: the send limits.
    case 'stageart_participant_invitation_send_limit': {
      const availableAt = formatServerDateTime(error.data?.available_at);
      return `このメールアドレスへの送信は30日間で6回までです。${availableAt ? `${availableAt}以降に送信できます。` : ''}`;
    }
    case 'stageart_participant_invitation_resend_limit':
      return 'この招待の再送は5回までです。取り消して新しく招待しても、30日間の送信回数は数え直されません。';
    case 'stageart_participant_invitation_recently_rejected': {
      const availableAt = formatServerDateTime(error.data?.available_at);
      return `このメールアドレスへの招待は辞退されたため、${availableAt ? `${availableAt}まで` : 'しばらくの間'}再び招待できません。`;
    }
    case 'stageart_participant_invitation_production_closed':
      return 'この公演は終了または中止されているため、招待・再送はできません。';
    case 'stageart_participant_invitation_inviter_revoked':
      return '招待した管理者の権限がなくなっているため、この招待は再送できません。取り消して、改めて招待してください。';
    case 'stageart_participant_invitation_not_pending':
      return 'この招待は既に承諾・辞退・取消などで処理済みのため、この操作はできません。';
    case 'stageart_participant_invitation_access_denied':
      return '招待の操作は、PrimaryManagerまたは参加者管理の権限を持つ担当者のみ行えます。';
    case 'stageart_participant_invitation_invalid':
      return '氏名・メールアドレス・役割を正しく入力してください。';
    // §7.2: the invitee's accept / reject.
    case 'stageart_participant_invitation_forbidden':
      return 'このアカウントでは招待に回答できません。メールアドレスの確認が済んでいるかご確認ください。';
    case 'stageart_participant_invitation_not_found':
      return 'この招待は見つかりません。招待一覧から確認してください。';
    case 'stageart_participant_invitation_expired':
      return 'この招待は有効期限が切れています。';
    case 'stageart_participant_invitation_closed':
      return 'この招待は現在承諾できません（公演の終了、または招待した管理者の権限がなくなったため）。';
    case 'stageart_participant_draft_exists':
      return 'この公演での参加の情報に確認が必要なため、承諾できません。公演の管理者に問い合わせてください。';
    // §7.1 / §7.2: participants.
    case 'stageart_participant_type_conflict':
      return 'この人は、変更先の区分で既にこの公演に登録されています。';
    case 'stageart_participant_cancel_not_allowed':
      return 'この状態のメンバーは削除できません。参加申請は「却下」を使ってください。';
    case 'stageart_participant_requires_invitation':
      return 'StageArtのアカウントを持つ方は、招待して本人に承諾してもらう必要があります。';
    case 'stageart_participant_status_change_not_allowed':
    case 'stageart_participant_status_requires_consent':
      return 'この状態の変更はできません。';
    case 'stageart_participant_withdraw_primary_manager':
      return '公演の管理者（PrimaryManager）は参加をやめられません。先に管理者を引き継いでください。';
    case 'stageart_participant_withdraw_not_allowed':
      return '現在参加中ではないため、参加をやめる操作はできません。';
    // §4.6: delegate appointment.
    case 'stageart_production_delegate_target_not_participant':
      return 'この方はこの公演のメンバーではないため、担当者にできません。招待して、承諾後に改めて任命してください。';
    case 'stageart_production_delegate_target_not_consented':
      return 'この方は同意が確認できていないため、担当者にできません。メンバー管理から「同意の確認を依頼」してください。';
    default:
      return null;
  }
}
