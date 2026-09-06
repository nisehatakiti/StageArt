import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { currentPerson, mockFetchRoutes, rehearsalConfirmed } from '@/features/attendance/__fixtures__/attendanceFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * StageArt Rehearsal機能完成度向上: the Edit screen fetches the existing
 * Rehearsal via useRehearsal(rehearsalId) (not a route-param payload) and
 * seeds title/date/time/endTime/location from it, mirroring
 * create.tsx's own "seed state from a query once" pattern. description
 * is deliberately not shown here (no input added), only carried through
 * on submit - see attendance-rehearsal-edit-submit.test.tsx.
 */
describe('Rehearsal edit screen: initial values', () => {
  it('populates the form from the fetched Rehearsal', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/me'), status: 200, body: currentPerson },
      { test: (u) => u.endsWith('/rehearsals/rehearsal-2'), status: 200, body: rehearsalConfirmed },
    ]);

    renderRouter('src/app', { initialUrl: '/production/prod-1/schedule/attendance/rehearsal-2/edit' });

    await waitFor(() => expect(screen.getByTestId('rehearsal-edit-title')).toBeVisible());

    expect(screen.getByTestId('rehearsal-edit-title').props.value).toBe(rehearsalConfirmed.title);
    expect(screen.getByTestId('rehearsal-edit-date').props.value).toBe('2026-08-20');
    expect(screen.getByTestId('rehearsal-edit-time').props.value).toBe('10:00');
    expect(screen.getByTestId('rehearsal-edit-end-time').props.value).toBe('12:00');
    expect(screen.getByTestId('rehearsal-edit-location').props.value).toBe(rehearsalConfirmed.location);
  });
});
