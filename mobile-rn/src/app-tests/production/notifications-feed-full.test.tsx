import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, notificationsTwo, productionOne } from './__fixtures__/productionShellFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

describe('Notifications tab: real TimetableVersionPublished Facts from the API', () => {
  it('renders each Notification with its title, summary, and published date', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne },
      { test: (u) => u.includes('/productions/prod-1/notifications'), status: 200, body: notificationsTwo },
    ]);

    renderRouter('src/app', { initialUrl: '/production/prod-1/notifications' });

    await waitFor(() => expect(screen.getAllByTestId('notification-row')).toHaveLength(2));

    expect(screen.getAllByText('タイムテーブルが更新されました')).toHaveLength(2);
    expect(screen.getByText('集合時間を30分繰り上げました。')).toBeVisible();
    expect(screen.getByText('2026/8/15 09:30')).toBeVisible();
  });
});
