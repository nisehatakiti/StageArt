import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';
import { Alert } from 'react-native';

import { currentPerson, mockFetchRoutes, pushPreferenceOn } from './__fixtures__/productionShellFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * §30, superseded by StageArt Blueprint再構成 Phase 1d: Logout now lives
 * on `/account` (features/account/AccountContent.tsx), not the
 * Production Shell's マイページ tab - see that Phase's report. Still
 * verified end-to-end through the real rendered app (see
 * useLogout.test.tsx for the isolated cache-clearing assertion).
 */
describe('Account: Logout navigates to /login', () => {
  it('confirms via the Alert and lands on the login screen', async () => {
    jest.spyOn(Alert, 'alert').mockImplementation((_title, _message, buttons) => {
      const destructive = buttons?.find((button) => button.style === 'destructive');
      destructive?.onPress?.();
    });

    mockFetchRoutes([
      { test: (u) => u.endsWith('/me'), status: 200, body: currentPerson },
      { test: (u) => u.includes('/me/push-preference'), status: 200, body: pushPreferenceOn },
    ]);

    renderRouter('src/app', { initialUrl: '/account' });

    await waitFor(() => expect(screen.getByTestId('account-logout-button')).toBeVisible());
    fireEvent.press(screen.getByTestId('account-logout-button'));

    await waitFor(() => expect(screen.getByTestId('login-email')).toBeVisible());
  });
});
