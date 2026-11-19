import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, rehearsals } from '@/features/attendance/__fixtures__/attendanceFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * A participant with no Production Role still sees the 予定 list, but not
 * 「＋ 予定を作成する」: CreateRehearsalUseCase accepts only the
 * PrimaryManager or a REHEARSAL_MANAGER, and such a participant cannot even
 * read GET /productions/{id} (403).
 */
describe('予定: 担当ロールのない参加者', () => {
  it('lists the Rehearsals without offering 予定を作成する', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 403, body: { code: 'stageart_production_access_denied', message: 'denied', data: { status: 403 } } },
      { test: (u) => u.endsWith('/productions/prod-1/rehearsals'), status: 200, body: rehearsals },
      { test: (u) => u.endsWith('/me/participating-productions'), status: 200, body: [{ participant_id: 'participant-1', production_id: 'prod-1', production_name: '○○公演2026', production_slug: null, participant_type: 'CAST' }] },
    ]);

    renderRouter('src/app', { initialUrl: '/production/prod-1/schedule/attendance' });

    await waitFor(() => expect(screen.getByTestId(`rehearsal-row-${rehearsals[0].id}`)).toBeVisible());
    await waitFor(() =>
      expect((global.fetch as jest.Mock).mock.calls.some(([url]) => String(url).endsWith('/productions/prod-1'))).toBe(true)
    );
    expect(screen.queryByTestId('rehearsal-create-link')).toBeNull();
  });
});
