/**
 * PR #7 R14-B (design doc §10 「ログイン」, R13 Medium): the `return_to` of
 * the login screen is followed only when it is a path inside this app -
 * one leading "/", no scheme or host ("//host", "https:", "/\\host"), no
 * backslash or control character - and not an authentication screen.
 * Anything else is ignored, so a link can never send a person to another
 * site after logging in. It never carries a token (the callers only pass
 * an app path such as `/participant-invitations`).
 *
 * @returns the path to go to after login, or null to use the usual route
 */
export function safeReturnTo(value: unknown): string | null {
  if (typeof value !== 'string' || value.length === 0 || value.length > 512) {
    return null;
  }
  if (!value.startsWith('/') || value.startsWith('//')) {
    return null;
  }
  if (/[\\\u0000-\u001f\u007f]/.test(value)) {
    return null;
  }
  const path = value.split(/[?#]/, 1)[0];
  if (path.includes(':')) {
    return null;
  }
  if (/^\/(login|register|registration-pending|verify-email|forgot-password|reset-password)(\/|$)/.test(path)) {
    return null;
  }
  return value;
}

/** The app paths that send an unauthenticated visitor to login with a return_to (§10: the invitations). */
export function returnToForUnauthenticated(pathname: string): string | null {
  return pathname === '/participant-invitations' || pathname.startsWith('/participant-invitations/') ? pathname : null;
}

/**
 * Carries a login return_to across the authentication screens (login ->
 * register -> registration-pending -> login), so an invited person who has
 * to register or confirm the address first still reaches the page they came
 * for. The value is checked with safeReturnTo() again on every hop; anything
 * that is not an in-app path is dropped and `path` is returned unchanged.
 *
 * @param path an app path, optionally with its own query (`?email=...`)
 */
export function withReturnTo(path: string, returnTo: unknown): string {
  const safe = safeReturnTo(returnTo);
  if (safe === null) {
    return path;
  }
  return `${path}${path.includes('?') ? '&' : '?'}return_to=${encodeURIComponent(safe)}`;
}
