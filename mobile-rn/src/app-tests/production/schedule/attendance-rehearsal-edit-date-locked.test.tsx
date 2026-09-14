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
 * StageArt Phase 7 (Rehearsal仕様整合) §3/§11: a CONFIRMED Rehearsal's
 * date can no longer be changed (Rehearsal::updateBasicInfo()'s own
 * guard) - this is the UX mirror of that Backend rule, not a substitute
 * for it.
 */
describe('Rehearsal edit screen: date locked once CONFIRMED', () => {
  it('disables the date input and shows an explanation for a CONFIRMED Rehearsal', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/me'), status: 200, body: currentPerson },
      { test: (u) => u.endsWith('/rehearsals/rehearsal-2'), status: 200, body: rehearsalConfirmed },
    ]);

    renderRouter('src/app', { initialUrl: '/production/prod-1/schedule/attendance/rehearsal-2/edit' });

    await waitFor(() => expect(screen.getByTestId('rehearsal-edit-date-locked-caption')).toBeVisible());

    expect(screen.getByTestId('rehearsal-edit-date').props.editable).toBe(false);
    expect(screen.getByTestId('rehearsal-edit-time').props.editable).not.toBe(false);
  });
});
