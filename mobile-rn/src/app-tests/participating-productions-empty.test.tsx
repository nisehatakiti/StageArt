import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * See participating-productions-shows-active-participant.test.tsx's
 * docblock for why this is kept in its own file.
 */
describe('参加している公演・活動', () => {
  it('shows the empty state, not an error, when the caller has no active Participant', async () => {
    mockFetchRoutes([{ test: (u) => u.endsWith('/me/participating-productions'), status: 200, body: [] }]);

    renderRouter('src/app', { initialUrl: '/participating-productions' });

    await waitFor(() => expect(screen.getByTestId('participating-productions-empty')).toBeVisible());
    expect(screen.queryByTestId('participating-productions-list')).toBeNull();
  });
});
