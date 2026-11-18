import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/** PR #7 R14-B stage 9 (design doc §10 / §14.2): the empty list's guidance text. */
describe('R14-B 招待一覧: 空', () => {
  it('shows the §10 guidance when there is no invitation', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/me/participant-invitations'), status: 200, body: [] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/participant-invitations' });

    await waitFor(() =>
      expect(screen.getByTestId('participant-invitations-empty')).toHaveTextContent(
        '招待が見つからない場合は、招待メールを受け取ったメールアドレスでログインしてください。登録したばかりの場合は、メールアドレスの確認を済ませてください。'
      )
    );
  });
});
