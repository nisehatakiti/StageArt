import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes } from './__fixtures__/scheduleFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/** §28: 403 (Authorization) - "このProductionを閲覧する権限がない" - is
 * shown distinctly from 401. */
describe('Schedule: fails to load with 403', () => {
  it('shows an Authorization-specific error message', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/me'), status: 200, body: { id: 'person-1', word_press_user_id: 1 } },
      { test: (u) => u.includes('/productions/prod-1/participants'), status: 200, body: [] },
      {
        test: (u) => u.includes('/productions/prod-1/timetable-items'),
        status: 403,
        body: { code: 'stageart_production_schedule_access_denied', message: 'Forbidden' },
      },
    ]);

    renderRouter('src/app', { initialUrl: '/production/prod-1/schedule' });

    await waitFor(() => expect(screen.getByTestId('schedule-error')).toBeVisible());
    expect(screen.getByText('この情報を表示する権限がありません。')).toBeVisible();
  });
});
