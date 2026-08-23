import type { ApiClient } from '@/api/client';
import { getApiBaseUrl } from '@/api/config';
import { ApiError, NetworkError } from '@/api/errors';
import type { AuthenticationResult, RefreshAccessTokenResult, UserAccountResult } from '@/types/api';

/**
 * The public (no StageArt session required yet) endpoints from the
 * Backend Phase 2 report: establishing a session is exactly what these
 * do, so they cannot go through ApiClient (which requires an Access
 * Token to already exist - see ApiClient.authHeader()). This is a
 * deliberately small, separate fetch wrapper mirroring ApiClient's own
 * error-decoding so callers still get the same ApiError/NetworkError
 * shape as every other API call in this app.
 */
async function publicPost<T>(path: string, body?: unknown): Promise<T> {
  let response: Response;

  try {
    response = await fetch(`${getApiBaseUrl()}${path}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: body !== undefined ? JSON.stringify(body) : undefined,
    });
  } catch (cause) {
    throw new NetworkError(cause);
  }

  if (response.ok) {
    const text = await response.text();
    return (text.length > 0 ? JSON.parse(text) : null) as T;
  }

  let message = `Request failed (${response.status})`;
  let code: string | undefined;

  try {
    const decoded = (await response.json()) as { message?: string; code?: string };
    message = decoded.message ?? message;
    code = decoded.code;
  } catch {
    // Non-JSON error body: keep the generic message above.
  }

  throw new ApiError(response.status, message, code);
}

export function registerWithEmail(email: string, password: string): Promise<AuthenticationResult> {
  return publicPost<AuthenticationResult>('/auth/email/register', { email, password });
}

export function loginWithEmail(email: string, password: string): Promise<AuthenticationResult> {
  return publicPost<AuthenticationResult>('/auth/email/login', { email, password });
}

/** `idToken` is the Google ID Token obtained from the native Google
 * Sign-In SDK (src/auth/googleSignIn.ts) - exchanged here, once, for a
 * StageArt Access/Refresh Token pair. Never used as an API credential
 * itself past this single call. */
export function loginWithGoogle(idToken: string): Promise<AuthenticationResult> {
  return publicPost<AuthenticationResult>('/auth/google', { id_token: idToken });
}

export function refreshAccessToken(refreshToken: string): Promise<RefreshAccessTokenResult> {
  return publicPost<RefreshAccessTokenResult>('/auth/refresh', { refresh_token: refreshToken });
}

/** Best-effort server-side revocation - AuthContext.logout() clears local
 * session state regardless of whether this call succeeds (see its own
 * docblock: a network failure here must never block signing the user out
 * of the app on this device). */
export function logout(refreshToken: string): Promise<{ success: boolean }> {
  return publicPost<{ success: boolean }>('/auth/logout', { refresh_token: refreshToken });
}

/** Always resolves the same way regardless of whether the email is
 * actually registered (Backend Phase's anti-enumeration design) - the UI
 * must show one generic "確認メールをお送りしました" message in both cases,
 * never branch on the response. */
export function requestPasswordReset(email: string): Promise<{ success: boolean }> {
  return publicPost<{ success: boolean }>('/auth/password/reset-request', { email });
}

export function resetPassword(token: string, newPassword: string): Promise<{ success: boolean }> {
  return publicPost<{ success: boolean }>('/auth/password/reset', { token, new_password: newPassword });
}

export function verifyEmail(token: string): Promise<{ success: boolean }> {
  return publicPost<{ success: boolean }>('/auth/email/verify', { token });
}

// --- Authenticated self-service (require an existing StageArt session) ---

export function changePassword(client: ApiClient, currentPassword: string, newPassword: string): Promise<{ success: boolean }> {
  return client.post<{ success: boolean }>('/user-accounts/email-credential/password', {
    current_password: currentPassword,
    new_password: newPassword,
  });
}

export function requestEmailVerification(client: ApiClient): Promise<{ success: boolean }> {
  return client.post<{ success: boolean }>('/user-accounts/email-credential/verify-request');
}

/** Lets an already-authenticated (Google-only, typically) user add an
 * Email+Password Credential to their own UserAccount - the existing
 * Backend Phase 2 endpoint, reused unchanged. */
export function addEmailCredential(client: ApiClient, email: string, password: string): Promise<UserAccountResult> {
  return client.post<UserAccountResult>('/user-accounts/email-credential', { email, password });
}

/** Lets an already-authenticated (Email+Password, typically) user link a
 * Google Identity to their own UserAccount - the existing Backend Phase 2
 * endpoint, reused unchanged. Never auto-linked by matching email
 * (Backend enforces this; this Client never attempts to infer it either). */
export function linkGoogleIdentity(client: ApiClient, idToken: string): Promise<UserAccountResult> {
  return client.post<UserAccountResult>('/user-accounts/external-identity', { id_token: idToken });
}

/**
 * StageArt Authentication Phase 6: always acts on the caller's own
 * Person (Backend resolves it from the Bearer Token, never a request
 * parameter - see UpdatePersonNameUseCase.php's docblock), so there is
 * no personId argument here to accidentally target someone else's
 * record.
 */
export function updatePersonName(
  client: ApiClient,
  familyName: string,
  givenName: string
): Promise<{ id: string; family_name: string; given_name: string }> {
  return client.put<{ id: string; family_name: string; given_name: string }>('/me/name', {
    family_name: familyName,
    given_name: givenName,
  });
}
