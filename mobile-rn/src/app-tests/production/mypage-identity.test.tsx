import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { currentPerson, mockFetchRoutes, productionOne, pushPreferenceOn } from './__fixtures__/productionShellFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * §27/§28, superseded by StageArt Blueprint再構成 Phase 1d: this
 * Production Shell tab renders ProfileContent (Person info only - see
 * that Phase's report for why), unchanged in that it still shows Person
 * ID behind an explicit "詳細情報を表示" toggle rather than as headline
 * content - the display name (from the same GET /me response's
 * family_name/given_name) takes that place now.
 */
describe('My Page: identity from GET /me', () => {
  it('shows the display name up front, and the Person ID hidden by default', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne },
      { test: (u) => u.endsWith('/me'), status: 200, body: currentPerson },
      { test: (u) => u.includes('/me/push-preference'), status: 200, body: pushPreferenceOn },
    ]);

    renderRouter('src/app', { initialUrl: '/production/prod-1/mypage' });

    await waitFor(() => expect(screen.getByTestId('profile-display-name')).toBeVisible());
    expect(screen.getByTestId('profile-display-name')).toHaveTextContent(`${currentPerson.family_name} ${currentPerson.given_name}`);

    expect(screen.queryByTestId('profile-person-id')).toBeNull();
    expect(screen.getByTestId('profile-details-toggle')).toBeVisible();
  });
});
