/**
 * Mirrors the WP REST error envelope ({ code, message, data: { status } })
 * confirmed in Phase 5.0's Backend Compatibility Report. `statusCode`
 * covers every status the existing API actually returns
 * (401/403/404/409/422/500) - see the Phase 5.0 report's API Client
 * design.
 */
export class ApiError extends Error {
  readonly statusCode: number;
  readonly code?: string;

  constructor(statusCode: number, message: string, code?: string) {
    super(message);
    this.name = 'ApiError';
    this.statusCode = statusCode;
    this.code = code;
  }
}

/** Distinguished from ApiError so callers can offer a "Retry" action
 * specifically for connectivity failures (see Phase 5.0's Offline /
 * Network Error section) without conflating them with the server's own
 * 4xx/5xx responses. */
export class NetworkError extends Error {
  constructor(cause: unknown) {
    super('Network request failed');
    this.name = 'NetworkError';
    this.cause = cause;
  }
}

export class NotAuthenticatedError extends Error {
  constructor() {
    super('No StageArt credentials are stored.');
    this.name = 'NotAuthenticatedError';
  }
}
