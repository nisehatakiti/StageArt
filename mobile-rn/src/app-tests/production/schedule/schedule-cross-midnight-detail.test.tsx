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
 * PR #7 R12 (docs/20-RehearsalManagementScreen.md §25): the Item Detail
 * screen shows that an item ends the next day. The expected values hold in
 * any device timezone (R12 ran it with TZ set to Asia/Tokyo, UTC,
 * America/New_York, America/Los_Angeles and Pacific/Kiritimati at Jest
 * start).
 */
describe('Schedule Item Detail: item crossing midnight', () => {
  it('shows the start date and 翌00:30', async () => {
    const item = { ...staffItem, start_date_time: '2026-12-31T23:30:00+09:00', end_date_time: '2027-01-01T00:30:00+09:00' };
    mockFetchRoutes([
      { test: (u) => u.endsWith('/me'), status: 200, body: currentPerson },
      { test: (u) => u.includes('/productions/prod-1/participants'), status: 200, body: participants },
      { test: (u) => u.endsWith('/timetable-items/item-staff'), status: 200, body: item },
      { test: (u) => u.includes('/timetable-items/item-staff/schedule-comments'), status: 200, body: comments },
    ]);

    renderRouter('src/app', { initialUrl: '/production/prod-1/schedule/item-staff' });

    await waitFor(() => expect(screen.getByText('2026/12/31（木）')).toBeVisible());
    expect(screen.getByText(/23:30 - 翌00:30/)).toBeVisible();
  });
});
