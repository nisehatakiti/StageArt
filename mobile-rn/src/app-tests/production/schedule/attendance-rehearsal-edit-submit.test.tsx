import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { currentPerson, mockFetchRoutes, rehearsalConfirmed } from '@/features/attendance/__fixtures__/attendanceFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * StageArt Rehearsal機能完成度向上 - 最重要の回帰防止テスト:
 * UpdateRehearsalUseCase (Rehearsal.php::updateBasicInfo()) applies every
 * field unconditionally, description included. This screen has no
 * description input (per this Phase's explicit scope), but that must
 * never mean the update request sends description as missing/null - the
 * fetched Rehearsal's own current description has to be echoed back
 * unchanged. Uses a fixture with a real (non-null) description
 * specifically so a regression (sending null/undefined instead) would be
 * visible here, not masked by the fixture already being null.
 */
describe('Rehearsal edit screen: submit', () => {
  it('calls PUT /rehearsals/{id} with the changed field and the existing description carried through unchanged', async () => {
    const rehearsalWithDescription = { ...rehearsalConfirmed, description: '飲み物と着替えを持参してください' };

    mockFetchRoutes([
      { test: (u) => u.endsWith('/me'), status: 200, body: currentPerson },
      { test: (u) => u.endsWith('/rehearsals/rehearsal-2'), status: 200, body: rehearsalWithDescription },
    ]);

    renderRouter('src/app', { initialUrl: '/production/prod-1/schedule/attendance/rehearsal-2/edit' });

    await waitFor(() => expect(screen.getByTestId('rehearsal-edit-location').props.value).toBe(rehearsalConfirmed.location));

    await fireEvent.changeText(screen.getByTestId('rehearsal-edit-location'), '稽古場B');
    await waitFor(() => expect(screen.getByTestId('rehearsal-edit-location').props.value).toBe('稽古場B'));
    fireEvent.press(screen.getByTestId('rehearsal-edit-submit'));

    await waitFor(() => {
      const updateCall = (global.fetch as jest.Mock).mock.calls.find(
        ([url, init]) => String(url).endsWith('/rehearsals/rehearsal-2') && init?.method === 'PUT'
      );
      expect(updateCall).toBeDefined();

      const body = JSON.parse(updateCall![1].body as string);
      expect(body.location).toBe('稽古場B');
      expect(body.description).toBe('飲み物と着替えを持参してください');
    });
  });
});
