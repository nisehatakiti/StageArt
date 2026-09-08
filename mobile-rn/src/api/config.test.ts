import Constants from 'expo-constants';

import { ApiClient } from './client';
import { getApiBaseUrl } from './config';
import { DEV_API_BASE_URL, PROD_API_BASE_URL } from './environment';
import { publicGet } from './publicClient';

/**
 * StageArt Development/Production API separation: config.ts's
 * getApiBaseUrl() is the single window every API Client (Authenticated
 * via client.ts, Public via publicClient.ts) reads the Base URL
 * through. This file proves that wiring end-to-end - not just that
 * environment.js's resolveApiBaseUrl() returns the right string (see
 * environment.test.ts for that), but that a Client actually issues its
 * fetch() calls against dev-api.stageart.top when Development is
 * configured, and api.stageart.top when Production is configured, with
 * neither Client hardcoding or duplicating a Base URL of its own.
 */
function setConfiguredApiBaseUrl(apiBaseUrl: string) {
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  (Constants as any).expoConfig = { extra: { apiEnv: 'test', apiBaseUrl } };
}

describe('getApiBaseUrl() and every API Client built on it', () => {
  afterEach(() => {
    setConfiguredApiBaseUrl(DEV_API_BASE_URL);
  });

  it('getApiBaseUrl() returns the Development API Base URL when so configured', () => {
    setConfiguredApiBaseUrl(DEV_API_BASE_URL);
    expect(getApiBaseUrl()).toBe('https://dev-api.stageart.top/wp-json/stageart/v1');
  });

  it('getApiBaseUrl() returns the Production API Base URL when so configured', () => {
    setConfiguredApiBaseUrl(PROD_API_BASE_URL);
    expect(getApiBaseUrl()).toBe('https://api.stageart.top/wp-json/stageart/v1');
  });

  it('ApiClient (Authenticated Client), with no explicit baseUrl override, calls the Development API when so configured', async () => {
    setConfiguredApiBaseUrl(DEV_API_BASE_URL);
    global.fetch = jest.fn().mockResolvedValue({ ok: true, status: 200, text: async () => '{}' });

    await new ApiClient(() => 'token').get('/me');

    const [url] = (global.fetch as jest.Mock).mock.calls[0];
    expect(url).toBe('https://dev-api.stageart.top/wp-json/stageart/v1/me');
  });

  it('ApiClient (Authenticated Client), with no explicit baseUrl override, calls the Production API when so configured', async () => {
    setConfiguredApiBaseUrl(PROD_API_BASE_URL);
    global.fetch = jest.fn().mockResolvedValue({ ok: true, status: 200, text: async () => '{}' });

    await new ApiClient(() => 'token').get('/me');

    const [url] = (global.fetch as jest.Mock).mock.calls[0];
    expect(url).toBe('https://api.stageart.top/wp-json/stageart/v1/me');
  });

  it('publicGet (Public Client) calls the Development API when so configured', async () => {
    setConfiguredApiBaseUrl(DEV_API_BASE_URL);
    global.fetch = jest.fn().mockResolvedValue({ ok: true, status: 200, text: async () => '{}' });

    await publicGet('/organizations/by-slug/example');

    const [url] = (global.fetch as jest.Mock).mock.calls[0];
    expect(url).toBe('https://dev-api.stageart.top/wp-json/stageart/v1/organizations/by-slug/example');
  });

  it('publicGet (Public Client) calls the Production API when so configured', async () => {
    setConfiguredApiBaseUrl(PROD_API_BASE_URL);
    global.fetch = jest.fn().mockResolvedValue({ ok: true, status: 200, text: async () => '{}' });

    await publicGet('/organizations/by-slug/example');

    const [url] = (global.fetch as jest.Mock).mock.calls[0];
    expect(url).toBe('https://api.stageart.top/wp-json/stageart/v1/organizations/by-slug/example');
  });
});
