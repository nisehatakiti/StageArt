import { ApiClient } from './client';
import { ApiError, NetworkError, NotAuthenticatedError } from './errors';

const BASE_URL = 'https://dev-stageart.hatakiti.com/stageart-test/wp-json/stageart/v1';

function mockFetchOnce(status: number, body: unknown) {
  (global.fetch as jest.Mock).mockResolvedValueOnce({
    ok: status >= 200 && status < 300,
    status,
    text: async () => JSON.stringify(body),
    json: async () => body,
  });
}

describe('ApiClient', () => {
  beforeEach(() => {
    global.fetch = jest.fn();
  });

  it('sends a Bearer Authorization header derived from the access token provider', async () => {
    mockFetchOnce(200, { id: 'person-1', word_press_user_id: 1 });

    const client = new ApiClient(() => 'access-token-abc', BASE_URL);
    await client.get('/me');

    const [, requestInit] = (global.fetch as jest.Mock).mock.calls[0];
    expect(requestInit.headers.Authorization).toBe('Bearer access-token-abc');
  });

  it('throws NotAuthenticatedError when no access token is available, without calling fetch', async () => {
    const client = new ApiClient(() => null, BASE_URL);

    await expect(client.get('/me')).rejects.toBeInstanceOf(NotAuthenticatedError);
    expect(global.fetch).not.toHaveBeenCalled();
  });

  it.each([403, 404, 409, 422, 500])('maps a %d response to ApiError with the correct statusCode', async (status) => {
    mockFetchOnce(status, { code: 'stageart_test_error', message: `failed with ${status}` });

    const client = new ApiClient(() => 'access-token', BASE_URL);

    try {
      await client.get('/productions');
      throw new Error('expected client.get to reject');
    } catch (error) {
      expect(error).toBeInstanceOf(ApiError);
      expect((error as ApiError).statusCode).toBe(status);
      expect((error as ApiError).code).toBe('stageart_test_error');
    }
  });

  it('wraps a fetch rejection (connectivity failure) in NetworkError', async () => {
    (global.fetch as jest.Mock).mockRejectedValueOnce(new TypeError('Failed to fetch'));

    const client = new ApiClient(() => 'access-token', BASE_URL);

    await expect(client.get('/productions')).rejects.toBeInstanceOf(NetworkError);
  });

  it('appends non-empty query parameters and omits empty/undefined ones', async () => {
    mockFetchOnce(200, []);

    const client = new ApiClient(() => 'access-token', BASE_URL);
    await client.get('/productions/prod-1/timetable-items', { from: '2026-08-17T00:00:00+09:00', to: '' });

    const [url] = (global.fetch as jest.Mock).mock.calls[0];
    expect(url).toContain('from=2026-08-17T00%3A00%3A00%2B09%3A00');
    expect(url).not.toContain('to=');
  });

  it('returns raw bytes from getBytes() without attempting JSON parsing on success', async () => {
    const bytes = new Uint8Array([0x25, 0x50, 0x44, 0x46]).buffer; // "%PDF"
    (global.fetch as jest.Mock).mockResolvedValueOnce({
      ok: true,
      status: 200,
      arrayBuffer: async () => bytes,
    });

    const client = new ApiClient(() => 'access-token', BASE_URL);
    const result = await client.getBytes('/productions/prod-1/timetable/print');

    expect(new Uint8Array(result)).toEqual(new Uint8Array([0x25, 0x50, 0x44, 0x46]));
  });

  describe('401 handling', () => {
    it('without onUnauthorized, surfaces a 401 as a normal ApiError', async () => {
      mockFetchOnce(401, { code: 'stageart_invalid_access_token', message: 'expired' });

      const client = new ApiClient(() => 'stale-token', BASE_URL);

      await expect(client.get('/me')).rejects.toMatchObject({ statusCode: 401 });
      expect(global.fetch).toHaveBeenCalledTimes(1);
    });

    it('on a 401, calls onUnauthorized once and retries with the refreshed token', async () => {
      mockFetchOnce(401, { code: 'stageart_invalid_access_token', message: 'expired' });
      mockFetchOnce(200, { id: 'person-1', word_press_user_id: 1 });

      const onUnauthorized = jest.fn().mockResolvedValue('fresh-token');
      let currentToken = 'stale-token';
      const client = new ApiClient(() => currentToken, BASE_URL, async () => {
        currentToken = await onUnauthorized();
        return currentToken;
      });

      const result = await client.get('/me');

      expect(result).toEqual({ id: 'person-1', word_press_user_id: 1 });
      expect(onUnauthorized).toHaveBeenCalledTimes(1);
      expect(global.fetch).toHaveBeenCalledTimes(2);

      const [, secondRequestInit] = (global.fetch as jest.Mock).mock.calls[1];
      expect(secondRequestInit.headers.Authorization).toBe('Bearer fresh-token');
    });

    it('when onUnauthorized cannot refresh (returns null), surfaces the original 401 without retrying', async () => {
      mockFetchOnce(401, { code: 'stageart_invalid_access_token', message: 'expired' });

      const onUnauthorized = jest.fn().mockResolvedValue(null);
      const client = new ApiClient(() => 'stale-token', BASE_URL, onUnauthorized);

      await expect(client.get('/me')).rejects.toMatchObject({ statusCode: 401 });
      expect(onUnauthorized).toHaveBeenCalledTimes(1);
      expect(global.fetch).toHaveBeenCalledTimes(1);
    });

    it('retries at most once even if the refreshed token also gets a 401', async () => {
      mockFetchOnce(401, { code: 'stageart_invalid_access_token', message: 'expired' });
      mockFetchOnce(401, { code: 'stageart_invalid_access_token', message: 'still expired' });

      const onUnauthorized = jest.fn().mockResolvedValue('fresh-token-that-also-fails');
      const client = new ApiClient(() => 'stale-token', BASE_URL, onUnauthorized);

      await expect(client.get('/me')).rejects.toMatchObject({ statusCode: 401 });
      expect(onUnauthorized).toHaveBeenCalledTimes(1);
      expect(global.fetch).toHaveBeenCalledTimes(2);
    });
  });
});
