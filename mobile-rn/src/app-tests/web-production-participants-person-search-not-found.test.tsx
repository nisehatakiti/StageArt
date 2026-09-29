import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty, productionOne } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * StageArt メンバー管理画面改善 §11 instruction: a nonexistent Person ID
 * must be handled cleanly - GET /people/{id} returns 404
 * (stageart_person_not_found) and the search shows an error, never a
 * crash or a silent add.
 */
describe('Web メンバー管理: 存在しないPerson IDの検索', () => {
  it('shows a not-found message for a nonexistent Person ID instead of adding anything', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne },
      { test: (u) => u.endsWith('/productions/prod-1/participants'), status: 200, body: [] },
      { test: (u) => u.endsWith('/productions/prod-1/participation-requests'), status: 200, body: [] },
      { test: (u) => u.endsWith('/people/no-such-person'), status: 404, body: { code: 'stageart_person_not_found', message: 'not found' } },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/productions/prod-1/participants' });

    await waitFor(() => expect(screen.getByTestId('production-participants-person-id-input')).toBeVisible());

    fireEvent.changeText(screen.getByTestId('production-participants-person-id-input'), 'no-such-person');
    await waitFor(() => expect(screen.getByTestId('production-participants-person-id-input').props.value).toBe('no-such-person'));
    fireEvent.press(screen.getByTestId('production-participants-person-search'));

    await waitFor(() => expect(screen.getByTestId('production-participants-person-search-error')).toBeVisible(), { timeout: 3000 });
    expect(screen.queryByTestId('production-participants-person-preview')).toBeNull();
  });
});
