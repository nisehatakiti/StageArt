import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { productionOne } from './__fixtures__/productionShellFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/** §14: a connectivity failure (fetch() itself rejecting, e.g. no
 * network) must be distinguished from a server-returned error status -
 * see src/api/errors.ts's NetworkError and errorMessage.ts's mapping. */
describe('Accounting tab: Network Error', () => {
  it(
    'shows the connectivity-specific message when fetch() itself fails',
    async () => {
      global.fetch = jest.fn(async (input: unknown) => {
        const url = String(input);

        if (url.endsWith('/auth/refresh')) {
          return {
            ok: true,
            status: 200,
            text: async () => JSON.stringify({ access_token: 'refreshed-token', token_type: 'Bearer', expires_in: 3600 }),
          } as Response;
        }

        if (url.endsWith('/productions/prod-1')) {
          return { ok: true, status: 200, text: async () => JSON.stringify(productionOne), json: async () => productionOne } as Response;
        }

        if (url.includes('/productions/prod-1/accounting')) {
          throw new TypeError('Network request failed');
        }

        throw new Error(`Unmocked fetch: ${url}`);
      });

      renderRouter('src/app', { initialUrl: '/production/prod-1/accounting' });

      // Unlike a 4xx ApiError, a NetworkError is retried by TanStack
      // Query's default policy (src/api/queryClient.ts's
      // shouldRetryQuery) - up to 2 extra attempts with real backoff
      // delays (~1s, ~2s) elapse before the error state settles, so this
      // needs a longer timeout than the default.
      await waitFor(() => expect(screen.getByTestId('accounting-error')).toBeVisible(), { timeout: 8000 });
      expect(screen.getByText('サーバーへ接続できませんでした。通信環境を確認してください。')).toBeVisible();
    },
    10000
  );
});
