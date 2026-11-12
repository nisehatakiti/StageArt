import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { currentPerson, pushPreferenceOff, pushPreferenceOn } from './__fixtures__/productionShellFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * StageArt Blueprint再構成 Phase 1d: Push Preference now lives on
 * `/account` (features/account/AccountContent.tsx), not the Production
 * Shell's マイページ tab - see that Phase's report.
 */
describe('Account: Push Preference', () => {
  it('shows the current ON state and turns it OFF via PUT /me/push-preference', async () => {
    let currentPreference = pushPreferenceOn;
    let putCalls = 0;

    global.fetch = jest.fn(async (input: unknown, init?: RequestInit) => {
      const url = String(input);

      if (url.endsWith('/auth/refresh')) {
        return {
          ok: true,
          status: 200,
          text: async () => JSON.stringify({ access_token: 'refreshed-token', token_type: 'Bearer', expires_in: 3600 }),
        } as Response;
      }

      if (url.endsWith('/me')) {
        return { ok: true, status: 200, text: async () => JSON.stringify(currentPerson), json: async () => currentPerson } as Response;
      }
      if (url.includes('/me/push-preference') && (!init || init.method === undefined || init.method === 'GET')) {
        return {
          ok: true,
          status: 200,
          text: async () => JSON.stringify(currentPreference),
          json: async () => currentPreference,
        } as Response;
      }
      if (url.includes('/me/push-preference') && init?.method === 'PUT') {
        putCalls += 1;
        const body = JSON.parse(String(init.body)) as { enabled: boolean };
        currentPreference = pushPreferenceOff.enabled === body.enabled ? pushPreferenceOff : pushPreferenceOn;
        return {
          ok: true,
          status: 200,
          text: async () => JSON.stringify(currentPreference),
          json: async () => currentPreference,
        } as Response;
      }

      throw new Error(`Unmocked fetch: ${url}`);
    });

    renderRouter('src/app', { initialUrl: '/account' });

    await waitFor(() => expect(screen.getByTestId('account-push-switch')).toBeVisible());
    expect(screen.getByTestId('account-push-switch').props.value).toBe(true);

    fireEvent(screen.getByTestId('account-push-switch'), 'valueChange', false);

    await waitFor(() => expect(screen.getByTestId('account-push-switch').props.value).toBe(false));
    expect(putCalls).toBe(1);
  });
});
