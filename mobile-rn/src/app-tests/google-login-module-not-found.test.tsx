import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * StageArt mobile-rn 修正指示書 §6: when neither the New Architecture
 * TurboModule proxy nor the legacy NativeModules bridge has
 * RNGoogleSignin registered - unconditionally true under Jest/RN's test
 * renderer, and true on every real Web deploy (react-native-web never
 * registers this native-only module) - login.tsx no longer offers
 * "Googleで続ける" at all. Previously this state still rendered a
 * tappable button that always failed the moment it was pressed, with a
 * diagnostic Alert as the only feedback; a real-user report confirmed
 * that "press it and nothing usable happens" is worse than not offering
 * the action, so isGoogleSignInAvailable() (googleSignIn.ts) now gates
 * the button's very existence instead. This test intentionally does not
 * mock googleSignIn.ts - the real, unmocked
 * checkGoogleSigninModuleStatus() already reports "not found" in this
 * environment, which is exactly the condition under test.
 */
describe('login flow: Google, native module not found', () => {
  it('does not render "Googleで続ける" at all', async () => {
    mockFetchRoutes([]);

    renderRouter('src/app', { initialUrl: '/login' });

    await waitFor(() => expect(screen.getByTestId('login-email')).toBeVisible());

    expect(screen.queryByTestId('login-google-button')).toBeNull();
    expect(screen.queryByText('Googleで続ける')).toBeNull();
  });
});
