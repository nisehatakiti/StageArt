import { ApiError, NetworkError } from '@/api/errors';

import { getErrorMessage } from './errorMessage';

describe('getErrorMessage', () => {
  it('distinguishes 401 (Authentication) from 403 (Authorization)', () => {
    expect(getErrorMessage(new ApiError(401, 'Unauthorized'))).toBe('認証が切れました。再度ログインしてください。');
    expect(getErrorMessage(new ApiError(403, 'Forbidden'))).toBe('この情報を表示する権限がありません。');
  });

  it('maps 404 and other ApiError status codes distinctly', () => {
    expect(getErrorMessage(new ApiError(404, 'Not Found'))).toBe('情報が見つかりませんでした。');
    expect(getErrorMessage(new ApiError(500, 'Server Error'))).toBe('読み込みに失敗しました。(500)');
  });

  it('tells a closed Production (409) and a busy server (503, nothing saved) apart', () => {
    expect(getErrorMessage(new ApiError(409, 'closed', 'stageart_production_closed'))).toBe(
      'この公演は中止または決算完了済みのため、この操作はできません。'
    );
    expect(getErrorMessage(new ApiError(503, 'busy', 'stageart_database_busy'))).toBe(
      'サーバーが混み合っていたため、保存されませんでした。少し待ってからもう一度お試しください。'
    );
    expect(getErrorMessage(new ApiError(503, 'Service Unavailable'))).toBe('読み込みに失敗しました。(503)');
  });

  it('explains a capacity below the reserved seats (409)', () => {
    expect(getErrorMessage(new ApiError(409, 'below', 'stageart_capacity_below_reservations'))).toBe(
      '予約済みの席数より少ない定員には変更できません。定員は変更されていません。'
    );
  });

  it('maps NetworkError to a connectivity-specific message', () => {
    expect(getErrorMessage(new NetworkError(new Error('offline')))).toBe(
      'サーバーへ接続できませんでした。通信環境を確認してください。'
    );
  });

  it('falls back to a generic message for unknown errors', () => {
    expect(getErrorMessage(new Error('mystery'))).toBe('読み込みに失敗しました。');
    expect(getErrorMessage('not even an Error')).toBe('読み込みに失敗しました。');
  });
});
