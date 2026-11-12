import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { currentPerson, mockFetchRoutes } from '@/features/attendance/__fixtures__/attendanceFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

describe('My notifications: empty state', () => {
  it('shows an empty-state message when there are no personal notifications', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/me'), status: 200, body: currentPerson },
      { test: (u) => u.endsWith('/me/notifications'), status: 200, body: [] },
    ]);

    renderRouter('src/app', { initialUrl: '/my-notifications' });

    await waitFor(() => expect(screen.getByTestId('my-notifications-empty')).toBeVisible());
  });
});
