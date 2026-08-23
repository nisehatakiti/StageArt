import { getApiBaseUrl } from './config';
import { ApiError, NetworkError, NotAuthenticatedError } from './errors';

/**
 * StageArt Authentication Phase 5: the credential is now the StageArt
 * Access Token issued by /auth/google or /auth/email/* (Bearer auth),
 * never the WordPress Application Password this Client used before, and
 * never the Google ID Token itself - the ID Token is only ever exchanged
 * once, server-side, for a StageArt token (see the Backend Phase 2
 * report's explicit requirement).
 */
export type CredentialsProvider = () => string | null;

/**
 * Invoked at most once per logical request, on a 401 response. Should
 * attempt a token refresh (via the stored Refresh Token) and return the
 * new Access Token on success, or null if the session could not be
 * restored (e.g. the Refresh Token itself is invalid/expired) - AuthContext
 * supplies this and is responsible for updating its own state/logging the
 * user out on failure, not this Client.
 */
export type UnauthorizedHandler = () => Promise<string | null>;

type QueryParams = Record<string, string | undefined>;

/**
 * Thin fetch() wrapper. Deliberately has no knowledge of any specific
 * StageArt feature (Production/Schedule/Notification/etc.) - those live
 * in src/features/*\/api.ts as plain functions built on top of this.
 *
 * Bearer Token only (see the Backend Phase 2 Authentication report):
 * StageArt's own JWT Access Token is sent as `Authorization: Bearer
 * <token>`. On a 401, this Client gives `onUnauthorized` exactly one
 * chance to refresh the session before retrying the original request
 * once; a second 401 (or no `onUnauthorized` handler) is surfaced as a
 * normal ApiError(401) to the caller, same as any other error status -
 * no feature-level code needs to know refresh happened at all.
 */
export class ApiClient {
  private readonly baseUrl: string;
  private readonly getAccessToken: CredentialsProvider;
  private readonly onUnauthorized?: UnauthorizedHandler;

  constructor(getAccessToken: CredentialsProvider, baseUrl: string = getApiBaseUrl(), onUnauthorized?: UnauthorizedHandler) {
    this.baseUrl = baseUrl;
    this.getAccessToken = getAccessToken;
    this.onUnauthorized = onUnauthorized;
  }

  private authHeader(): string {
    const accessToken = this.getAccessToken();

    if (!accessToken) {
      throw new NotAuthenticatedError();
    }

    return `Bearer ${accessToken}`;
  }

  private buildUrl(path: string, query?: QueryParams): string {
    const url = new URL(`${this.baseUrl}${path}`);

    if (query) {
      for (const [key, value] of Object.entries(query)) {
        if (value !== undefined && value !== '') {
          url.searchParams.set(key, value);
        }
      }
    }

    return url.toString();
  }

  private async request(
    method: string,
    path: string,
    options?: { query?: QueryParams; body?: unknown },
    isRetry = false
  ): Promise<Response> {
    const url = this.buildUrl(path, options?.query);
    const headers: Record<string, string> = {
      Authorization: this.authHeader(),
      Accept: 'application/json',
    };

    let body: string | undefined;
    if (options?.body !== undefined) {
      headers['Content-Type'] = 'application/json';
      body = JSON.stringify(options.body);
    }

    let response: Response;
    try {
      response = await fetch(url, { method, headers, body });
    } catch (cause) {
      throw new NetworkError(cause);
    }

    if (response.status === 401 && !isRetry && this.onUnauthorized) {
      const refreshedToken = await this.onUnauthorized();

      if (refreshedToken) {
        return this.request(method, path, options, true);
      }
    }

    return response;
  }

  private async decodeJson(response: Response): Promise<unknown> {
    if (response.ok) {
      const text = await response.text();
      return text.length > 0 ? JSON.parse(text) : null;
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

  async get<T = unknown>(path: string, query?: QueryParams): Promise<T> {
    const response = await this.request('GET', path, { query });
    return this.decodeJson(response) as Promise<T>;
  }

  async post<T = unknown>(path: string, body?: unknown): Promise<T> {
    const response = await this.request('POST', path, { body });
    return this.decodeJson(response) as Promise<T>;
  }

  async put<T = unknown>(path: string, body?: unknown): Promise<T> {
    const response = await this.request('PUT', path, { body });
    return this.decodeJson(response) as Promise<T>;
  }

  async delete<T = unknown>(path: string): Promise<T> {
    const response = await this.request('DELETE', path);
    return this.decodeJson(response) as Promise<T>;
  }

  /** Phase 7.1: the first PATCH-verb endpoint this Client calls
   * (Notification mark-read - see NotificationRestController.php's
   * `PATCH /notifications/{id}/read`). Same shape as put(). */
  async patch<T = unknown>(path: string, body?: unknown): Promise<T> {
    const response = await this.request('PATCH', path, { body });
    return this.decodeJson(response) as Promise<T>;
  }

  /** For binary responses (Print View's PDF endpoint - see Phase 5.0's
   * Print View section). Bypasses JSON decoding entirely on success. */
  async getBytes(path: string, query?: QueryParams): Promise<ArrayBuffer> {
    const response = await this.request('GET', path, { query });

    if (!response.ok) {
      await this.decodeJson(response);
    }

    return response.arrayBuffer();
  }
}
