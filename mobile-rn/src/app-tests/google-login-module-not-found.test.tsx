import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';
import { Alert } from 'react-native';

import { mockFetchRoutes } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

const mockSignInWithGoogle = jest.fn(async () => 'mock-google-id-token');
jest.mock('@/auth/googleSignIn', () => ({
  ...jest.requireActual('@/auth/googleSignIn'),
  signInWithGoogle: mockSignInWithGoogle,
}));

jest.mock('@/auth/googleModuleDiagnostics', () => ({
  ...jest.requireActual('@/auth/googleModuleDiagnostics'),
  checkGoogleSigninModuleStatus: jest.fn(() => ({
    turboModuleFound: false,
    legacyBridgeModuleFound: false,
    otherGoogleRelatedKeys: [],
  })),
}));

/**
 * StageArt Google認証 診断 (2026-08-23): when a real device's installed
 * binary predates the RNGoogleSignin native module (or the module is
 * otherwise unregistered), login.tsx's pre-flight
 * checkGoogleSigninModuleStatus() probe must surface a specific,
 * diagnostic message via Alert.alert (an imperative native call, not
 * dependent on this screen's own state/re-render cycle - deliberately
 * not asserted via testID="login-error" visibility here, since that
 * would only prove the *pre-existing*, unrelated setErrorMessage() UI
 * path re-renders under this test harness, which this suite cannot
 * establish either way for ANY of login.tsx's error paths, including
 * the untouched Email+Password one - see this session's report).
 * signInWithGoogle() must never even be called in this case.
 */
describe('login flow: Google, native module not found', () => {
  it('shows a diagnostic Alert and never calls signInWithGoogle()', async () => {
    const alertSpy = jest.spyOn(Alert, 'alert').mockImplementation(() => {});
    mockFetchRoutes([]);

    renderRouter('src/app', { initialUrl: '/login' });

    await waitFor(() => expect(screen.getByTestId('login-google-button')).toBeVisible());

    fireEvent.press(screen.getByTestId('login-google-button'));

    await waitFor(() => expect(alertSpy).toHaveBeenCalled());
    const [title, message] = alertSpy.mock.calls[0];
    expect(title).toBe('Googleサインイン診断');
    expect(message).toContain('RNGoogleSignin');
    expect(mockSignInWithGoogle).not.toHaveBeenCalled();
  });
});
