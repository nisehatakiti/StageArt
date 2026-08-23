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
 * Blueprint v1.5 alignment Phase: Home's own header no longer carries a
 * logout button (the common layout's header is the StageArt logo only -
 * BusinessFlowUXClarifications.md §04) - logout now lives on the
 * Profile screen (プロフィール, one of Home's own 5 primary entry
 * points), reusing the same full useLogout() sequence
 * (Auth state + SecureStore clear + Query cache clear + navigate to
 * /login) this test originally verified directly from Home.
 */
describe('Home -> Profile: logout button', () => {
  it('uses the full logout sequence and navigates to /login', async () => {
    jest.spyOn(Alert, 'alert').mockImplementation((_title, _message, buttons) => {
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

    await waitFor(() => expect(screen.getByTestId('home-nav-profile')).toBeVisible());
    fireEvent.press(screen.getByTestId('home-nav-profile'));

    await waitFor(() => expect(screen.getByTestId('mypage-logout-button')).toBeVisible());
    fireEvent.press(screen.getByTestId('mypage-logout-button'));

    await waitFor(() => expect(screen.getByTestId('login-email')).toBeVisible());
  });
});
