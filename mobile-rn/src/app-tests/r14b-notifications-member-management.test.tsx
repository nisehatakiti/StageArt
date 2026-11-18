import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * PR #7 R14-B stage 9 (design doc §10 「アプリ内通知」 / §11.1): a notice
 * about a member leaving opens the Production's member management.
 */
describe('R14-B お知らせ: メンバー管理へ', () => {
  it('opens member management from a participant_withdrawn notice', async () => {
    mockFetchRoutes([
      {
        test: (u) => u.endsWith('/me/notifications'),
        status: 200,
        body: [
          {
            id: 'notification-1',
            type: 'participant_withdrawn',
            message: '出演 太郎さんが『○○公演2026』の参加をやめました',
            production_id: 'prod-1',
            rehearsal_id: null,
            is_read: true,
            created_at: '2026-11-18T00:00:00+00:00',
          },
        ],
      },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    const router = renderRouter('src/app', { initialUrl: '/my-notifications' });

    await waitFor(() => expect(screen.getByText('出演 太郎さんが『○○公演2026』の参加をやめました')).toBeVisible());
    fireEvent.press(screen.getByText('出演 太郎さんが『○○公演2026』の参加をやめました'));
    await waitFor(() => expect(router.getPathname()).toBe('/productions/prod-1/participants'));
  });
});
