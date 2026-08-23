import { QueryClient } from '@tanstack/react-query';

import { ApiError } from './errors';

/**
 * TanStack Query's default retry (3 attempts with backoff) is correct
 * for transient failures (NetworkError, 5xx) but wrong for 401/403/404/
 * 422 - a permission or not-found error will not resolve by retrying
 * the same request, and retrying only delays the UI from showing the
 * real error (found via this Phase's own Home error-state tests: a
 * mocked 401/403 response stayed "loading" for the default multi-second
 * backoff window before Home's error branch ever rendered).
 */
export function shouldRetryQuery(failureCount: number, error: unknown): boolean {
  if (error instanceof ApiError && error.statusCode >= 400 && error.statusCode < 500) {
    return false;
  }

  return failureCount < 2;
}

export function createQueryClient(): QueryClient {
  return new QueryClient({
    defaultOptions: {
      queries: {
        retry: shouldRetryQuery,
      },
    },
  });
}
