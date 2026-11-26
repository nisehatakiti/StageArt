import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { castItem, currentPerson, mockFetchRoutes, participants, staffItem } from './__fixtures__/scheduleFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * PR #7 R11 (docs/20-RehearsalManagementScreen.md §24): the 予定 list shows
 * a 10:00 JST item as 10:00 on a device outside Japan, whether the API
 * gives the time with +09:00 or as the same instant in UTC. Before R11 the
 * list used the device's local time (New York: 21:00 the day before).
 *
 * The expected values hold on a device in any timezone; R11 ran this file
 * with the process timezone set to Asia/Tokyo, UTC, America/New_York,
 * America/Los_Angeles and Pacific/Kiritimati (TZ at Jest start - Jest
 * does not apply a change to process.env.TZ made inside a test file).
 */
describe('Schedule list: Asia/Tokyo times on any device', () => {
  it('shows 10:00 JST items at 10:00 under the Tokyo date', async () => {
    const jstItem = { ...staffItem, start_date_time: '2026-08-18T10:00:00+09:00', end_date_time: '2026-08-18T12:00:00+09:00' };
    const utcItem = { ...castItem, start_date_time: '2026-08-18T01:00:00Z', end_date_time: '2026-08-18T03:30:00Z' };
    mockFetchRoutes([
      { test: (u) => u.endsWith('/me'), status: 200, body: currentPerson },
      { test: (u) => u.includes('/productions/prod-1/participants'), status: 200, body: participants },
      { test: (u) => u.includes('/productions/prod-1/timetable-items'), status: 200, body: [jstItem, utcItem] },
    ]);

    renderRouter('src/app', { initialUrl: '/production/prod-1/schedule' });

    await waitFor(() => expect(screen.getByTestId('schedule-item-item-staff')).toBeVisible());
    expect(screen.getByTestId('schedule-item-item-staff')).toHaveTextContent(/10:00 - 12:00/);
    expect(screen.getByTestId('schedule-item-item-cast')).toHaveTextContent(/10:00 - 12:30/);
    expect(screen.getAllByText('2026/8/18（火）')).toHaveLength(1);
    expect(screen.queryByText(/21:00/)).toBeNull();
  });
});
