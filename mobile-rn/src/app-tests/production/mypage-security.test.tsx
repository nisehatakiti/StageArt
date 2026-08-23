import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { currentPerson, mockFetchRoutes, productionOne, pushPreferenceOn } from './__fixtures__/productionShellFixtures';

const SECRET_REFRESH_TOKEN = 'super-secret-refresh-token-xyz';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? SECRET_REFRESH_TOKEN : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/** StageArt Authentication Phase 5's security checklist (successor to
 * the pre-Phase-5 Application Password check): the Refresh Token never
 * appears in rendered UI text and never passes through console.*,
 * regardless of which screen is showing. */
describe('My Page: Credential never appears in UI or logs', () => {
  it('never renders or logs the Refresh Token', async () => {
    const consoleSpies = [
      jest.spyOn(console, 'log').mockImplementation(() => {}),
      jest.spyOn(console, 'warn').mockImplementation(() => {}),
      jest.spyOn(console, 'error').mockImplementation(() => {}),
      jest.spyOn(console, 'info').mockImplementation(() => {}),
    ];

    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne },
      { test: (u) => u.endsWith('/me'), status: 200, body: currentPerson },
      { test: (u) => u.includes('/me/push-preference'), status: 200, body: pushPreferenceOn },
    ]);

    renderRouter('src/app', { initialUrl: '/production/prod-1/mypage' });

    await waitFor(() => expect(screen.getByTestId('mypage-person-id')).toBeVisible());

    expect(screen.queryByText(SECRET_REFRESH_TOKEN)).toBeNull();

    for (const spy of consoleSpies) {
      for (const call of spy.mock.calls) {
        expect(call.join(' ')).not.toContain(SECRET_REFRESH_TOKEN);
      }
      spy.mockRestore();
    }
  });
});
