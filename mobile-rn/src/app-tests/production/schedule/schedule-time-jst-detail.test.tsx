import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { comments, currentPerson, mockFetchRoutes, participants, staffItem } from './__fixtures__/scheduleFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * PR #7 R11 (docs/20-RehearsalManagementScreen.md §24): the Item Detail
 * screen shows the same Asia/Tokyo date and time as the list and the
 * editor, on a device outside Japan.
 *
 * The expected values hold on a device in any timezone; R11 ran this file
 * with the process timezone set to Asia/Tokyo, UTC, America/New_York,
 * America/Los_Angeles and Pacific/Kiritimati (TZ at Jest start - Jest
 * does not apply a change to process.env.TZ made inside a test file).
 */
describe('Schedule Item Detail: Asia/Tokyo time on any device', () => {
  it('shows a 00:30 JST item on the Tokyo date at 00:30', async () => {
    const item = { ...staffItem, start_date_time: '2026-08-17T15:30:00Z', end_date_time: '2026-08-18T02:00:00+09:00' };
    mockFetchRoutes([
      { test: (u) => u.endsWith('/me'), status: 200, body: currentPerson },
      { test: (u) => u.includes('/productions/prod-1/participants'), status: 200, body: participants },
      { test: (u) => u.endsWith('/timetable-items/item-staff'), status: 200, body: item },
      { test: (u) => u.includes('/timetable-items/item-staff/schedule-comments'), status: 200, body: comments },
    ]);

    renderRouter('src/app', { initialUrl: '/production/prod-1/schedule/item-staff' });

    await waitFor(() => expect(screen.getByText('2026/8/18（火）')).toBeVisible());
    expect(screen.getByText(/00:30 - 02:00/)).toBeVisible();
  });
});
