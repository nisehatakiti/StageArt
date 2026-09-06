import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import {
  attendanceConfirmationRoster,
  currentPerson,
  mockFetchRoutes,
  productionOne,
  rehearsalConfirmed,
} from '@/features/attendance/__fixtures__/attendanceFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * StageArt Rehearsal機能完成度向上: location (and start_date_time) are
 * optional on the Domain (Rehearsal.md's own nullable fields) - the
 * Detail screen's new info block follows the exact same "render nothing,
 * no placeholder text" pattern the Rehearsal list screen (schedule/
 * attendance/index.tsx) already established for this same optional
 * field, rather than inventing new copy like「場所未設定」.
 */
describe('Attendance detail: Rehearsal with no location/date set', () => {
  it('renders no location or datetime line, without any placeholder text', async () => {
    const rehearsalWithoutLocationOrDate = { ...rehearsalConfirmed, location: null, start_date_time: null, end_date_time: null };

    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne },
      { test: (u) => u.endsWith('/me'), status: 200, body: currentPerson },
      { test: (u) => u.endsWith('/rehearsals/rehearsal-2'), status: 200, body: rehearsalWithoutLocationOrDate },
      {
        test: (u) => u.includes('/rehearsals/rehearsal-2/attendances'),
        status: 200,
        body: attendanceConfirmationRoster,
      },
    ]);

    renderRouter('src/app', { initialUrl: '/production/prod-1/schedule/attendance/rehearsal-2' });

    await waitFor(() => expect(screen.getByTestId('rehearsal-info-status')).toBeVisible());
    expect(screen.getByTestId('rehearsal-info-status')).toHaveTextContent('確定');
    expect(screen.queryByTestId('rehearsal-info-datetime')).toBeNull();
    expect(screen.queryByTestId('rehearsal-info-location')).toBeNull();
    expect(screen.queryByText('場所未設定')).toBeNull();
    expect(screen.queryByText('場所なし')).toBeNull();
  });
});
