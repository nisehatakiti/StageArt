import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, participatingProductionOne } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * 「参加している公演・活動」のデータソース修正ラウンド: this screen now
 * reads GET /me/participating-productions (the caller's own ACTIVE
 * PERSON Participant rows, joined to Production - see
 * ListMyParticipatingProductionsUseCase.php), not GET /me/dashboard's
 * upcoming_rehearsals. The Backend, not this screen, is responsible for
 * the ACTIVE/PERSON filtering (see that UseCase's own PHPUnit coverage
 * for the CANCELLED/NAME_ONLY/no-rehearsal-required cases) - this test
 * only confirms the screen renders the server's rows as-is.
 *
 * Kept in its own file, matching home-dashboard-error-productions-ok.test.tsx's
 * documented precedent: pairing this with the empty-state test in one
 * file empirically hit Expo Router testing-library's cross-test
 * renderRouter() query-cache leak (the second render reused the first
 * render's cached ['my-participating-productions'] result) - a test-
 * environment limitation, not an application defect.
 */
describe('参加している公演・活動', () => {
  it('shows a Production the caller has an active Participant in', async () => {
    mockFetchRoutes([{ test: (u) => u.endsWith('/me/participating-productions'), status: 200, body: [participatingProductionOne] }]);

    renderRouter('src/app', { initialUrl: '/participating-productions' });

    await waitFor(() => expect(screen.getByTestId('participating-productions-list')).toBeVisible());
    expect(screen.getByTestId(`participating-production-row-${participatingProductionOne.production_id}`)).toBeVisible();
    expect(screen.getByText('踊れチュパカブラ')).toBeVisible();
  });
});
