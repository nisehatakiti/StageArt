import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { currentPerson, mockFetchRoutes, participants } from './__fixtures__/scheduleFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/** §27: Empty State distinguishes "no Items in this (default) range"
 * from a Production with no schedule at all, via the wording and the
 * "全期間を表示する" shortcut shown only in the default-range case. */
describe('Schedule: empty default-range result', () => {
  it('shows a range-specific Empty State with a shortcut to Full Range', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/me'), status: 200, body: currentPerson },
      { test: (u) => u.includes('/productions/prod-1/participants'), status: 200, body: participants },
      { test: (u) => u.includes('/productions/prod-1/timetable-items'), status: 200, body: [] },
    ]);

    renderRouter('src/app', { initialUrl: '/production/prod-1/schedule' });

    await waitFor(() => expect(screen.getByTestId('schedule-empty')).toBeVisible());
    expect(screen.getByText('この期間に予定はありません。')).toBeVisible();
    expect(screen.getByTestId('schedule-empty-show-full-range')).toBeVisible();
  });
});
