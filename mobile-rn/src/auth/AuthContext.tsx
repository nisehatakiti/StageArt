import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState, type PropsWithChildren } from 'react';

import { ApiClient } from '@/api/client';
import { ApiError, NetworkError } from '@/api/errors';
import {
  loginWithEmail as loginWithEmailApi,
  loginWithGoogle as loginWithGoogleApi,
  logout as logoutApi,
  refreshAccessToken as refreshAccessTokenApi,
  registerWithEmail as registerWithEmailApi,
} from '@/features/auth/api';
import { fetchCurrentPerson } from '@/features/production/api';
import type { AuthenticationResult } from '@/types/api';

import { clearTokens, loadStoredTokens, saveAccessToken, saveTokens } from './secureStorage';
import { getPendingSession, setPendingSession } from './pendingVerificationAccess';

/**
 * StageArt Authentication Phase 5: the four states this Phase's
 * instruction requires - 未ログイン (unauthenticated) / ログイン済み
 * (authenticated) / Token Refresh中 (refreshing) / plus the initial
 * `loading` read of SecureStore before either is known. "ログイン中"
 * (a login/register submit in flight) and "ログアウト中" stay local
 * `submitting` state on each screen, exactly as the pre-Phase-5 code
 * already did (see login.tsx/mypage.tsx) - they are UI-level concerns of
 * a single action, not session-lifecycle states shared app-wide.
 */
type AuthStatus = 'loading' | 'refreshing' | 'authenticated' | 'unauthenticated';

/**
 * `emailVerified`/`hasName` on the success case: a real device confirmed
 * that treating "registration/login API call succeeded" as "the user is
 * now logged into StageArt" was wrong for an unverified Email+Password
 * account - it left them not on a login screen but on a screen only
 * reachable while already "logged in" (registration-pending), on every
 * subsequent app launch, since the session had been persisted.
 * loginWithEmail/registerWithEmail/loginWithGoogle all decide whether to
 * persist a session (accessTokenRef/refreshTokenRef + SecureStore +
 * status='authenticated') strictly based on `emailVerified` - false
 * means no session is established at all; the caller routes to
 * registration-pending instead, using pendingVerificationAccess.ts's
 * transient, non-persisted token pair for that screen's resend action.
 *
 * StageArt Authentication Phase 6: `hasName` is the equivalent gate for
 * family_name/given_name - both Email and Google callers are routed to
 * set-name.tsx the same way when it's false (no "Google skips this"
 * exception - see login.tsx/index.tsx). `familyNameHint`/`givenNameHint`
 * are UI hints only (see AuthenticationResult's own docblock) - always
 * null for Email, sometimes populated for Google - carried through
 * purely so login.tsx can forward them to set-name.tsx's default form
 * values when `hasName` is false.
 */
type AuthActionResult =
  | { ok: true; emailVerified: boolean; hasName: boolean; familyNameHint: string | null; givenNameHint: string | null }
  | { ok: false; message: string };

type AuthContextValue = {
  status: AuthStatus;
  apiClient: ApiClient;
  loginWithEmail: (email: string, password: string) => Promise<AuthActionResult>;
  registerWithEmail: (email: string, password: string) => Promise<AuthActionResult>;
  loginWithGoogle: (idToken: string) => Promise<AuthActionResult>;
  logout: () => Promise<void>;
  completeEmailVerificationFromPending: () => Promise<{ hasSession: boolean; hasName: boolean }>;
};

const AuthContext = createContext<AuthContextValue | null>(null);

function mapNetworkOrGenericError(error: unknown, fallback: string): string {
  if (error instanceof NetworkError) {
    return 'サーバーへ接続できませんでした。通信環境を確認してください。';
  }
  if (error instanceof ApiError) {
    return fallback;
  }
  return fallback;
}

function hasNameFromPerson(person: { family_name: string | null; given_name: string | null }): boolean {
  return !!person.family_name && !!person.given_name;
}

export function AuthProvider({ children }: PropsWithChildren) {
  const [status, setStatus] = useState<AuthStatus>('loading');

  // Read by ApiClient's closures below. A ref, not the `status` state
  // above, because a token refresh triggered mid-request (ApiClient's
  // onUnauthorized) must be visible to that *same* ApiClient instance's
  // retry immediately - before React has had a chance to re-render and
  // hand out a new closure (see this Phase's report §"Token Refresh").
  const accessTokenRef = useRef<string | null>(null);
  const refreshTokenRef = useRef<string | null>(null);

  const applySession = useCallback(async (result: Pick<AuthenticationResult, 'access_token' | 'refresh_token'>) => {
    // A real, persisted session is starting - any leftover transient
    // token pair from an earlier, since-superseded unverified attempt is
    // no longer relevant.
    setPendingSession(null);
    accessTokenRef.current = result.access_token;
    refreshTokenRef.current = result.refresh_token;
    await saveTokens({ accessToken: result.access_token, refreshToken: result.refresh_token });
    setStatus('authenticated');
  }, []);

  /**
   * One-off, unauthenticated-in-the-persisted-sense GET /me probe using
   * a freshly-issued Access Token that has not (yet) been decided to be
   * worth persisting - used to answer "is this account's email verified,
   * and does it already have a name" right after a login, before
   * committing to a real session. A network failure here fails open to
   * "nothing is blocking" (verified, has-a-name) rather than stranding
   * an already-legitimate user behind a gate over a transient hiccup -
   * mirrors the same fail-open choice this app already made elsewhere
   * for email_verified (previously in index.tsx's boot gate).
   */
  const probePersonGate = useCallback(async (accessToken: string): Promise<{ emailVerified: boolean; hasName: boolean }> => {
    try {
      const probeClient = new ApiClient(() => accessToken);
      const person = await fetchCurrentPerson(probeClient);
      return { emailVerified: person.email_verified, hasName: hasNameFromPerson(person) };
    } catch {
      return { emailVerified: true, hasName: true };
    }
  }, []);

  const clearSession = useCallback(async () => {
    accessTokenRef.current = null;
    refreshTokenRef.current = null;
    await clearTokens();
    setStatus('unauthenticated');
  }, []);

  /**
   * Exchanges the stored Refresh Token for a fresh Access Token. Used
   * both at app boot (session restore) and reactively, as ApiClient's
   * onUnauthorized handler, whenever any request meets a 401 mid-session.
   * On failure (Refresh Token itself invalid/expired/revoked), the
   * session is torn down and the app returns to `unauthenticated` -
   * there is no retry-forever loop and no silent stuck state.
   */
  const performRefresh = useCallback(async (): Promise<string | null> => {
    const refreshToken = refreshTokenRef.current;

    if (!refreshToken) {
      return null;
    }

    try {
      const result = await refreshAccessTokenApi(refreshToken);
      accessTokenRef.current = result.access_token;
      await saveAccessToken(result.access_token);
      setStatus('authenticated');
      return result.access_token;
    } catch {
      await clearSession();
      return null;
    }
  }, [clearSession]);

  useEffect(() => {
    let cancelled = false;

    (async () => {
      const stored = await loadStoredTokens();

      if (cancelled) return;

      if (!stored) {
        setStatus('unauthenticated');
        return;
      }

      accessTokenRef.current = stored.accessToken || null;
      refreshTokenRef.current = stored.refreshToken;
      setStatus('refreshing');
      await performRefresh();
    })();

    return () => {
      cancelled = true;
    };
    // performRefresh is stable (useCallback, deps=[clearSession] which is
    // itself stable) - this effect is meant to run exactly once, at mount.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Wrapped in its own useCallback (matching performRefresh above) so
  // the ref read itself happens inside a deferred callback body, never
  // directly inline during render.
  const getAccessToken = useCallback(() => accessTokenRef.current, []);

  // A single, stable ApiClient instance for the whole session: its
  // closures read from the refs above, not from a captured `status`/
  // token variable, so it never needs to be recreated on login/refresh/
  // logout (see the ref comment above for why that matters specifically
  // for the retry-after-refresh path). getAccessToken/performRefresh are
  // themselves ref-reading callbacks, so react-hooks/refs (React
  // Compiler's stricter ESLint rule set) still flags merely *passing*
  // them into a constructor called from useMemo's factory - even though
  // neither is invoked here, only stored for later, deferred use inside
  // ApiClient's own request()/onUnauthorized flow. This is exactly that
  // safe, deferred-invocation pattern, not a same-render ref read; the
  // disable below is a deliberate, narrow exception, not a workaround
  // for an actual bug.
  const apiClient = useMemo(
    // eslint-disable-next-line react-hooks/refs
    () => new ApiClient(getAccessToken, undefined, performRefresh),
    [getAccessToken, performRefresh]
  );

  const loginWithEmail = useCallback(
    async (email: string, password: string): Promise<AuthActionResult> => {
      try {
        const result = await loginWithEmailApi(email, password);
        const { emailVerified, hasName } = await probePersonGate(result.access_token);
        if (emailVerified) {
          await applySession(result);
        } else {
          setPendingSession({ accessToken: result.access_token, refreshToken: result.refresh_token });
        }
        return { ok: true, emailVerified, hasName, familyNameHint: null, givenNameHint: null };
      } catch (error) {
        if (error instanceof ApiError && error.statusCode === 401) {
          return { ok: false, message: 'メールアドレスまたはパスワードが正しくありません。' };
        }
        return { ok: false, message: mapNetworkOrGenericError(error, 'ログインに失敗しました。時間をおいて再度お試しください。') };
      }
    },
    [applySession, probePersonGate]
  );

  const registerWithEmail = useCallback(async (email: string, password: string): Promise<AuthActionResult> => {
    try {
      const result = await registerWithEmailApi(email, password);
      // A freshly created EmailCredential is never pre-verified
      // (Backend Domain guarantee: EmailCredential.emailVerifiedAt
      // starts null), and a freshly created Person never has a name set
      // either - unlike loginWithEmail there is nothing to probe here,
      // both are known false by construction. No session is
      // established; the caller routes to registration-pending.
      setPendingSession({ accessToken: result.access_token, refreshToken: result.refresh_token });
      return { ok: true, emailVerified: false, hasName: false, familyNameHint: null, givenNameHint: null };
    } catch (error) {
      if (error instanceof ApiError && error.statusCode === 409) {
        return { ok: false, message: 'このメールアドレスは既に登録されています。' };
      }
      if (error instanceof ApiError && error.statusCode === 422) {
        return { ok: false, message: 'パスワードは8文字以上で入力してください。' };
      }
      return { ok: false, message: mapNetworkOrGenericError(error, '新規登録に失敗しました。時間をおいて再度お試しください。') };
    }
  }, []);

  const loginWithGoogle = useCallback(
    async (idToken: string): Promise<AuthActionResult> => {
      try {
        const result = await loginWithGoogleApi(idToken);
        // Google-registered accounts are always email_verified: true
        // (GetCurrentPersonUseCase.php's docblock - no EmailCredential
        // exists to be unverified) - always safe to establish a real
        // session directly. hasName still needs a probe: a RETURNING
        // Google user may already have a stored name from a previous
        // session (must skip set-name.tsx), while a first-time Google
        // user does not (see AuthenticationResult's own
        // familyNameHint/givenNameHint - a *hint* for set-name.tsx's
        // default values, never a substitute for this check - StageArt
        // Authentication Phase 6's explicit "Googleだから直接Homeへ、と
        // いう例外を作らない" requirement).
        await applySession(result);
        const { hasName } = await probePersonGate(result.access_token);
        return {
          ok: true,
          emailVerified: true,
          hasName,
          familyNameHint: result.family_name_hint,
          givenNameHint: result.given_name_hint,
        };
      } catch (error) {
        if (error instanceof ApiError && error.statusCode === 401) {
          return { ok: false, message: 'Googleアカウントでの認証に失敗しました。もう一度お試しください。' };
        }
        return { ok: false, message: mapNetworkOrGenericError(error, 'Googleアカウントでのログインに失敗しました。') };
      }
    },
    [applySession, probePersonGate]
  );

  /**
   * StageArt Authentication Phase 6: called by verify-email.tsx right
   * after a successful POST /auth/email/verify, to continue seamlessly
   * to set-name/Home instead of forcing the user back to a login screen
   * they don't need - but only when this is the SAME app session that
   * registered/attempted-login (pendingVerificationAccess.ts's in-memory
   * state, which cannot exist in a separate device's or the Web
   * confirmation page's own JS process). `hasSession: false` tells the
   * caller to fall back to "確認完了しました。ログイン画面へ" instead.
   */
  const completeEmailVerificationFromPending = useCallback(async (): Promise<{ hasSession: boolean; hasName: boolean }> => {
    const pending = getPendingSession();

    if (!pending) {
      return { hasSession: false, hasName: false };
    }

    await applySession({ access_token: pending.accessToken, refresh_token: pending.refreshToken });
    const { hasName } = await probePersonGate(pending.accessToken);
    return { hasSession: true, hasName };
  }, [applySession, probePersonGate]);

  const logout = useCallback(async () => {
    const refreshToken = refreshTokenRef.current;

    // Best-effort server-side revocation: a network failure here must
    // never block signing the user out of this device (LogoutUseCase is
    // idempotent server-side, so a duplicate/late call is harmless too).
    if (refreshToken) {
      try {
        await logoutApi(refreshToken);
      } catch {
        // Ignored - see docblock above.
      }
    }

    await clearSession();
  }, [clearSession]);

  const value = useMemo(
    () => ({
      status,
      apiClient,
      loginWithEmail,
      registerWithEmail,
      loginWithGoogle,
      logout,
      completeEmailVerificationFromPending,
    }),
    [status, apiClient, loginWithEmail, registerWithEmail, loginWithGoogle, logout, completeEmailVerificationFromPending]
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);

  if (!context) {
    throw new Error('useAuth() must be used within an AuthProvider.');
  }

  return context;
}
