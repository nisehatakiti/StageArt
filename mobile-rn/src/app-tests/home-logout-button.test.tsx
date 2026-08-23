import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';
import { Alert } from 'react-native';

import { mockFetchRoutes, myDashboardEmpty, orgOne } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * StageArt Google/ログアウト優先修正: an explicit logout button lives on
 * Home itself now, in addition to the existing Profile-based one (see
 * home-logout.test.tsx) - reuses the exact same useLogout() sequence and
 * Alert.alert confirmation pattern as MyPageContent.tsx's button.
 */
describe('Home: explicit logout button', () => {
  it('shows a confirmation dialog, then logs out and navigates to /login', async () => {
    jest.spyOn(Alert, 'alert').mockImplementation((title, message, buttons) => {
      expect(title).toBe('ログアウト');
      expect(message).toBe('ログアウトしますか？');
      const destructive = buttons?.find((button) => button.style === 'destructive');
      destructive?.onPress?.();
    });

    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [orgOne] },
      { test: (u) => u.endsWith('/projects'), status: 200, body: [] },
      { test: (u) => u.endsWith('/productions'), status: 200, body: [] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
      { test: (u) => u.endsWith('/me/push-preference'), status: 200, body: { enabled: false, updated_at: null } },
    ]);

    renderRouter('src/app', { initialUrl: '/home' });

    await waitFor(() => expect(screen.getByTestId('home-logout-button')).toBeVisible());
    fireEvent.press(screen.getByTestId('home-logout-button'));

    await waitFor(() => expect(screen.getByTestId('login-email')).toBeVisible());
  });
});
