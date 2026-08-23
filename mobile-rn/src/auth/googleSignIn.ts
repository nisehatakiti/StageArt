import { getGoogleWebClientId } from '@/api/config';

export class GoogleSignInNotConfiguredError extends Error {
  constructor() {
    super('Google Cloud Console setup is not complete yet (no Web Client ID configured).');
    this.name = 'GoogleSignInNotConfiguredError';
  }
}

export class GoogleSignInCancelledError extends Error {
  constructor() {
    super('The user cancelled the Google Sign-In flow.');
    this.name = 'GoogleSignInCancelledError';
  }
}

/**
 * Triggers native Google Sign-In and returns the resulting Google ID
 * Token - never used as a StageArt API credential itself, only ever
 * handed to POST /auth/google once, server-side, for exchange (Backend
 * Phase 2 report's explicit requirement; see src/features/auth/api.ts's
 * loginWithGoogle()).
 *
 * `@react-native-google-signin/google-signin` is imported dynamically,
 * inside this function, rather than at module top level: it is a native
 * module, and the currently-installed EAS Development Build binary on
 * any device predates adding this dependency (see this Phase's report).
 * A top-level import would throw the instant this *file* is loaded -
 * which happens as soon as the login screen mounts, i.e. on every app
 * launch - breaking Email/Password login too, on the existing binary,
 * for an unrelated feature. A dynamic import confines that failure to
 * the moment the user actually taps "Googleで続ける", after which a new
 * EAS Development Build (produced once Google Cloud Console setup is
 * complete) resolves it for good.
 *
 * Package version pinned via `package.json` to the "Original Google sign
 * in" API surface (GoogleSignin.configure/hasPlayServices/signIn) -
 * confirmed via the package's own published TypeScript definitions for
 * @react-native-google-signin/google-signin@16.1.4, not the newer,
 * separate "Universal Sign In" hosted product this library also
 * advertises.
 */
export async function signInWithGoogle(): Promise<string> {
  const webClientId = getGoogleWebClientId();

  if (!webClientId) {
    throw new GoogleSignInNotConfiguredError();
  }

  const { GoogleSignin } = await import('@react-native-google-signin/google-signin');

  GoogleSignin.configure({ webClientId });

  await GoogleSignin.hasPlayServices({ showPlayServicesUpdateDialog: true });
  const response = await GoogleSignin.signIn();

  if (response.type === 'cancelled') {
    throw new GoogleSignInCancelledError();
  }

  const idToken = response.data.idToken;

  if (!idToken) {
    throw new Error('Google Sign-In did not return an ID Token.');
  }

  return idToken;
}
