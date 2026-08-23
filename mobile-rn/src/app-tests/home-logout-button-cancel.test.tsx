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

/** Cancelling Home's explicit logout confirmation must not clear the
 * session or navigate away (see home-logout-button.test.tsx for the
 * confirmed-logout counterpart). */
describe('Home: explicit logout button, cancelled', () => {
  it('stays on Home if the confirmation dialog is cancelled', async () => {
    jest.spyOn(Alert, 'alert').mockImplementation((_title, _message, buttons) => {
      const cancel = buttons?.find((button) => button.style === 'cancel');
      expect(cancel?.onPress).toBeUndefined();
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

    expect(screen.getByTestId('home-logout-button')).toBeVisible();
  });
});
