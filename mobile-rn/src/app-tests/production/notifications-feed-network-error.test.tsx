import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { productionOne } from './__fixtures__/productionShellFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

describe('Notifications tab: Network Error', () => {
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

        if (url.includes('/productions/prod-1/notifications')) {
          throw new TypeError('Network request failed');
        }

        throw new Error(`Unmocked fetch: ${url}`);
      });

      renderRouter('src/app', { initialUrl: '/production/prod-1/notifications' });

      await waitFor(() => expect(screen.getByTestId('notifications-error')).toBeVisible(), { timeout: 8000 });
      expect(screen.getByText('サーバーへ接続できませんでした。通信環境を確認してください。')).toBeVisible();
    },
    10000
  );
});
