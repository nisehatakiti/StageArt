import { ApiError } from './errors';
import { shouldRetryQuery } from './queryClient';

describe('shouldRetryQuery', () => {
  it('never retries 4xx ApiErrors (401/403/404/422)', () => {
    for (const status of [401, 403, 404, 422]) {
      expect(shouldRetryQuery(0, new ApiError(status, 'x'))).toBe(false);
    }
  });

  it('retries a 5xx ApiError up to the failure-count cap', () => {
    expect(shouldRetryQuery(0, new ApiError(500, 'x'))).toBe(true);
    expect(shouldRetryQuery(1, new ApiError(500, 'x'))).toBe(true);
    expect(shouldRetryQuery(2, new ApiError(500, 'x'))).toBe(false);
  });

  it('retries a plain Error (e.g. NetworkError) up to the failure-count cap', () => {
    expect(shouldRetryQuery(0, new Error('offline'))).toBe(true);
    expect(shouldRetryQuery(2, new Error('offline'))).toBe(false);
  });
});
