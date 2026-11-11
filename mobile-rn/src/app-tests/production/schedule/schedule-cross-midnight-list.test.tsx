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
 * PR #7 R12 (docs/20-RehearsalManagementScreen.md §25): an item crossing
 * midnight is listed under its start day and shows its end as 「翌」.
 * The expected values hold in any device timezone (R12 ran it with TZ set
 * to Asia/Tokyo, UTC, America/New_York, America/Los_Angeles and
 * Pacific/Kiritimati at Jest start).
 */
describe('Schedule list: items crossing midnight', () => {
  it('lists 23:30-翌00:30 under the start day', async () => {
    const late = { ...staffItem, start_date_time: '2026-11-10T23:30:00+09:00', end_date_time: '2026-11-10T16:30:00Z' };
    const morning = { ...castItem, start_date_time: '2026-11-11T09:00:00+09:00', end_date_time: '2026-11-11T10:00:00+09:00' };
    mockFetchRoutes([
      { test: (u) => u.endsWith('/me'), status: 200, body: currentPerson },
      { test: (u) => u.includes('/productions/prod-1/participants'), status: 200, body: participants },
      { test: (u) => u.includes('/productions/prod-1/timetable-items'), status: 200, body: [morning, late] },
    ]);

    renderRouter('src/app', { initialUrl: '/production/prod-1/schedule' });

    await waitFor(() => expect(screen.getByTestId('schedule-item-item-staff')).toBeVisible());
    expect(screen.getByTestId('schedule-item-item-staff')).toHaveTextContent(/23:30 - 翌01:30/);
    expect(screen.getByTestId('schedule-item-item-cast')).toHaveTextContent(/09:00 - 10:00/);
    expect(screen.getByText('2026/11/10（火）')).toBeVisible();
    expect(screen.getByText('2026/11/11（水）')).toBeVisible();
  });
});
