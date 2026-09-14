import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { currentPerson, mockFetchRoutes, rehearsalScheduleAdjustment } from '@/features/attendance/__fixtures__/attendanceFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * StageArt Phase 7 (Rehearsal仕様整合) §4: 回答期限 pre-fills from the
 * fetched Rehearsal and is sent, in the confirmed date+time input split
 * this screen already uses for start/end time, as `response_deadline` on
 * save.
 */
describe('Rehearsal edit screen: 回答期限', () => {
  it('pre-fills the existing deadline and sends the edited one on save', async () => {
    const rehearsalWithDeadline = { ...rehearsalScheduleAdjustment, response_deadline: '2026-08-19T18:00:00+09:00' };

    mockFetchRoutes([
      { test: (u) => u.endsWith('/me'), status: 200, body: currentPerson },
      { test: (u) => u.endsWith('/rehearsals/rehearsal-1'), status: 200, body: rehearsalWithDeadline },
    ]);

    renderRouter('src/app', { initialUrl: '/production/prod-1/schedule/attendance/rehearsal-1/edit' });

    await waitFor(() => expect(screen.getByTestId('rehearsal-edit-deadline-date').props.value).toBe('2026-08-19'));
    expect(screen.getByTestId('rehearsal-edit-deadline-time').props.value).toBe('18:00');

    fireEvent.changeText(screen.getByTestId('rehearsal-edit-deadline-date'), '2026-08-18');
    await waitFor(() => expect(screen.getByTestId('rehearsal-edit-deadline-date').props.value).toBe('2026-08-18'));
    fireEvent.press(screen.getByTestId('rehearsal-edit-submit'));

    await waitFor(() => {
      const updateCall = (global.fetch as jest.Mock).mock.calls.find(
        ([url, init]) => String(url).endsWith('/rehearsals/rehearsal-1') && init?.method === 'PUT'
      );
      expect(updateCall).toBeDefined();

      const body = JSON.parse(updateCall![1].body as string);
      expect(body.response_deadline).toBe('2026-08-18T18:00:00+09:00');
    });
  });
});
