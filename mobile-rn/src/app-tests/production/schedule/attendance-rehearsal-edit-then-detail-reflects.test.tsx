import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { currentPerson, productionOne, rehearsalConfirmed } from '@/features/attendance/__fixtures__/attendanceFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * StageArt Rehearsal機能完成度向上: end-to-end confirmation that the
 * Detail screen's new info block (rehearsal-info-location) is not stale
 * after an edit - Detail -> Edit -> change location -> save -> back to
 * Detail must show the updated value, not the value the screen first
 * loaded with. A stateful fetch mock is used (not the shared
 * mockFetchRoutes helper, which returns a fixed body per route) since
 * the same GET /rehearsals/{id} URL must legitimately return different
 * data before and after the PUT, mirroring what the real Backend does.
 */
describe('Rehearsal detail <-> edit round trip', () => {
  it('shows the updated location on Detail after editing and saving', async () => {
    let currentLocation = rehearsalConfirmed.location;

    global.fetch = jest.fn(async (input: unknown, init?: RequestInit) => {
      const url = String(input);

      if (url.endsWith('/auth/refresh')) {
        return { ok: true, status: 200, text: async () => JSON.stringify({ access_token: 'x', token_type: 'Bearer', expires_in: 3600 }) } as Response;
      }
      if (url.endsWith('/me')) {
        return { ok: true, status: 200, text: async () => JSON.stringify(currentPerson) } as Response;
      }
      if (url.endsWith('/productions/prod-1')) {
        return { ok: true, status: 200, text: async () => JSON.stringify(productionOne) } as Response;
      }
      if (url.includes('/rehearsals/rehearsal-2/attendances')) {
        return { ok: true, status: 200, text: async () => JSON.stringify([]) } as Response;
      }
      if (url.endsWith('/rehearsals/rehearsal-2') && init?.method === 'PUT') {
        const body = JSON.parse(init.body as string);
        currentLocation = body.location;
        return { ok: true, status: 200, text: async () => JSON.stringify({ ...rehearsalConfirmed, location: currentLocation }) } as Response;
      }
      if (url.endsWith('/rehearsals/rehearsal-2')) {
        return { ok: true, status: 200, text: async () => JSON.stringify({ ...rehearsalConfirmed, location: currentLocation }) } as Response;
      }

      throw new Error(`Unmocked fetch: ${url}`);
    }) as jest.Mock;

    renderRouter('src/app', { initialUrl: '/production/prod-1/schedule/attendance/rehearsal-2' });

    await waitFor(() => expect(screen.getByTestId('rehearsal-info-location')).toHaveTextContent('稽古場A'));

    fireEvent.press(screen.getByTestId('rehearsal-edit'));

    await waitFor(() => expect(screen.getByTestId('rehearsal-edit-location')).toBeVisible());
    await waitFor(() => expect(screen.getByTestId('rehearsal-edit-location').props.value).toBe('稽古場A'));

    await fireEvent.changeText(screen.getByTestId('rehearsal-edit-location'), '稽古場B');
    await waitFor(() => expect(screen.getByTestId('rehearsal-edit-location').props.value).toBe('稽古場B'));
    fireEvent.press(screen.getByTestId('rehearsal-edit-submit'));

    await waitFor(() => expect(screen.getByTestId('rehearsal-info-location')).toHaveTextContent('稽古場B'));
  });
});
