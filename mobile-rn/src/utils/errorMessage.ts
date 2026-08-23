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
