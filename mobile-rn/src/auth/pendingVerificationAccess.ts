/**
 * Holds, in memory only (never SecureStore, never React/AuthContext
 * state), the Access+Refresh Token pair returned by a register/login
 * call whose account turned out to be email-unverified.
 *
 * StageArt deliberately does not treat an unverified account as a
 * logged-in session: AuthContext.status never becomes 'authenticated'
 * for it and nothing is persisted (see AuthContext.tsx's loginWithEmail/
 * registerWithEmail docblocks) - a real device confirmed this was
 * necessary, since a persisted session meant an unverified user landed
 * back on registration-pending on every subsequent app launch instead
 * of the login screen.
 *
 * The Backend still hands back a valid, short-lived token pair on that
 * same register/login call, and this pending session is reused for two
 * things: registration-pending.tsx's resend-confirmation-email button
 * (access token only), and - StageArt Authentication Phase 6 -
 * verify-email.tsx's seamless continuation straight through to set-name/
 * Home when the SAME app session that registered also completes
 * verification, without forcing a redundant "please log in again" step
 * (see AuthContext.tsx's completeEmailVerificationFromPending()). Being
 * a plain module-scope variable rather than AuthContext state, it is
 * naturally gone on app restart (the JS module re-initializes) and
 * never exists at all in a separate device's/browser's JS process
 * (verifying from a different device or the Web confirmation page has
 * no way to reach this module's state) - both are intentional, not
 * gaps.
 */
export type PendingSession = { accessToken: string; refreshToken: string };

let pendingSession: PendingSession | null = null;

export function setPendingSession(session: PendingSession | null): void {
  pendingSession = session;
}

export function getPendingSession(): PendingSession | null {
  return pendingSession;
}
