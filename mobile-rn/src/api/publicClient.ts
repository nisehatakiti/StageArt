import { getApiBaseUrl } from './config';
import { ApiError, NetworkError } from './errors';

/**
 * Shared fetch wrapper for StageArt REST endpoints that take no Access
 * Token (`permission_callback => '__return_true'` Backend-side) -
 * originally written inline in features/auth/api.ts for the pre-session
 * auth endpoints, extracted here in Web First Phase 2 so the new public
 * `by-slug` Organization/Production reads (also unauthenticated) don't
 * duplicate the same error-decoding logic. Mirrors ApiClient's own
 * ApiError/NetworkError shape so callers get identical error handling
 * regardless of which client made the request.
 */
async function publicRequest<T>(method: 'GET' | 'POST', path: string, body?: unknown): Promise<T> {
  let response: Response;

  try {
    response = await fetch(`${getApiBaseUrl()}${path}`, {
      method,
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

export function publicGet<T>(path: string): Promise<T> {
  return publicRequest<T>('GET', path);
}

export function publicPost<T>(path: string, body?: unknown): Promise<T> {
  return publicRequest<T>('POST', path, body);
}
