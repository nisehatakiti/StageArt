import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';
import { Alert } from 'react-native';

import { mockFetchRoutes } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

jest.mock('@/auth/googleSignIn', () => ({
  ...jest.requireActual('@/auth/googleSignIn'),
  signInWithGoogleDiagnostic: jest.fn(async () => ({
    ok: false,
    cancelled: false,
    steps: [
      { step: "TurboModuleRegistry.get('RNGoogleSignin')", status: 'error', detail: 'not found' },
      { step: 'NativeModules.RNGoogleSignin', status: 'error', detail: 'not found' },
    ],
  })),
}));

/**
 * StageArt Google認証 段階的診断 (2026-08-23): when neither the New
 * Architecture TurboModule proxy nor the legacy NativeModules bridge has
 * RNGoogleSignin registered, signInWithGoogleDiagnostic() returns
 * `ok: false` with a step-by-step trace instead of letting
 * @react-native-google-signin/google-signin's own
 * TurboModuleRegistry.getEnforcing() throw its raw invariant message -
 * login.tsx must surface every step of that trace via Alert.alert
 * (an imperative native call, independent of this screen's own
 * re-render cycle - see this session's report on why testID="login-error"
 * visibility cannot be reliably asserted in this test harness for ANY of
 * login.tsx's error paths, including the untouched Email+Password one).
 */
describe('login flow: Google, native module not found', () => {
  it('shows the full diagnostic trace via Alert', async () => {
    const alertSpy = jest.spyOn(Alert, 'alert').mockImplementation(() => {});
    mockFetchRoutes([]);

    renderRouter('src/app', { initialUrl: '/login' });

    await waitFor(() => expect(screen.getByTestId('login-google-button')).toBeVisible());

    fireEvent.press(screen.getByTestId('login-google-button'));

    await waitFor(() => expect(alertSpy).toHaveBeenCalled());
    const [title, message] = alertSpy.mock.calls[0];
    expect(title).toBe('Googleサインイン診断');
    expect(message).toContain("TurboModuleRegistry.get('RNGoogleSignin')");
    expect(message).toContain('NativeModules.RNGoogleSignin');
    expect(message).toContain('not found');
  });
});
