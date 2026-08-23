import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes } from './__fixtures__/scheduleFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/** §28: 401 (Authentication) is shown distinctly - kept in its own
 * file, see schedule-error-403.test.tsx for the counterpart and Phase
 * 5.2's documented reason for this split. */
describe('Schedule: fails to load with 401', () => {
  it('shows an Authentication-specific error message', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/me'), status: 200, body: { id: 'person-1', word_press_user_id: 1 } },
      { test: (u) => u.includes('/productions/prod-1/participants'), status: 200, body: [] },
      {
        test: (u) => u.includes('/productions/prod-1/timetable-items'),
        status: 401,
        body: { code: 'stageart_unauthorized', message: 'Unauthorized' },
      },
    ]);

    renderRouter('src/app', { initialUrl: '/production/prod-1/schedule' });

    await waitFor(() => expect(screen.getByTestId('schedule-error')).toBeVisible());
    expect(screen.getByText('認証が切れました。再度ログインしてください。')).toBeVisible();
  });
});
