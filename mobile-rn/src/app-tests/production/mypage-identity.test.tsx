import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { currentPerson, mockFetchRoutes, productionOne, pushPreferenceOn } from './__fixtures__/productionShellFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/** §27/§28: only what GET /me actually returns - no display-name field
 * exists Backend-side, so the Person ID is shown rather than an invented
 * name. */
describe('My Page: identity from GET /me', () => {
  it('shows the Person ID returned by GET /me', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne },
      { test: (u) => u.endsWith('/me'), status: 200, body: currentPerson },
      { test: (u) => u.includes('/me/push-preference'), status: 200, body: pushPreferenceOn },
    ]);

    renderRouter('src/app', { initialUrl: '/production/prod-1/mypage' });

    await waitFor(() => expect(screen.getByTestId('mypage-person-id')).toBeVisible());
    expect(screen.getByTestId('mypage-person-id')).toHaveTextContent('person-1');
  });
});
