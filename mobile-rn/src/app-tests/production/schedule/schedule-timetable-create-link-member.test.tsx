import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { currentPerson, mockFetchRoutes, participants, productionOne, staffItem } from './__fixtures__/scheduleFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/** PR #7 R8 (2-5): a member who cannot edit Timetables (neither the
 * PrimaryManager nor a REHEARSAL_MANAGER) still sees and prints the
 * published timetable, but is not offered 「＋ タイムテーブルを作成」. */
describe('Schedule: タイムテーブルを作成 for a member without the role', () => {
  it('shows 印刷 but not 作成', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/me'), status: 200, body: currentPerson },
      {
        test: (u) => u.endsWith('/productions/prod-1'),
        status: 200,
        body: { ...productionOne, is_primary_manager: false, delegate_roles: [] },
      },
      { test: (u) => u.includes('/productions/prod-1/participants'), status: 200, body: participants },
      { test: (u) => u.includes('/productions/prod-1/timetable-items'), status: 200, body: [staffItem] },
    ]);

    renderRouter('src/app', { initialUrl: '/production/prod-1/schedule' });

    await waitFor(() => expect(screen.getByTestId('schedule-print-link')).toBeVisible());
    await waitFor(() => expect(screen.getByTestId('schedule-day-list')).toBeVisible());
    expect(screen.queryByTestId('schedule-create-link')).toBeNull();
  });
});
