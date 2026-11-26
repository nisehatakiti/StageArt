/**
 * Hands an e-mail address from one auth screen to the next, so the user
 * does not have to type it again:
 *
 * - `login`: the confirmation screen (app/verify-email.tsx) -> the login
 *   screen (the address just verified), and the registration screen's
 *   「ログインへ戻る」 -> the login screen;
 * - `register`: the login screen's 「新規登録へ」 (an address the account
 *   check reported as not registered) -> the registration screen.
 *
 * Kept in memory only - never in the URL, storage or a cookie - so the
 * address does not end up in the browser history, server logs or a later
 * session. It survives client-side navigation (`router.replace` / `push`)
 * and is lost on a full reload, in which case the next screen simply
 * starts empty as before. The receiving screen reads it once and clears it.
 */
export type EmailHandoffTarget = 'login' | 'register';

const pending: Record<EmailHandoffTarget, string | null> = { login: null, register: null };

function normalize(email: string | null | undefined): string | null {
  const trimmed = typeof email === 'string' ? email.trim() : '';
  return trimmed.length > 0 ? trimmed : null;
}

export function setEmailHandoff(target: EmailHandoffTarget, email: string | null | undefined): void {
  pending[target] = normalize(email);
}

/** The handed-over address, without clearing it (safe to call from a useState initializer). */
export function peekEmailHandoff(target: EmailHandoffTarget): string | null {
  return pending[target];
}

export function clearEmailHandoff(target: EmailHandoffTarget): void {
  pending[target] = null;
}

/** The login screen's slot (kept for the confirmation screen and existing callers). */
export function setLoginEmailHandoff(email: string | null | undefined): void {
  setEmailHandoff('login', email);
}

export function peekLoginEmailHandoff(): string | null {
  return peekEmailHandoff('login');
}

export function clearLoginEmailHandoff(): void {
  clearEmailHandoff('login');
}
