import { ApiError, NetworkError } from '@/api/errors';

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
    // PR #7 review (R6): a capacity below the seats already reserved.
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
