import { Platform } from 'react-native';

import { getGoogleWebClientId } from '@/api/config';
import { checkGoogleSigninModuleStatus } from '@/auth/googleModuleDiagnostics';

/**
 * StageArt mobile-rn 修正指示書 §6: whether "Googleで続ける"/"Googleアカウント
 * を連携" should even be offered as a tappable action, decided from the
 * same two preconditions signInWithGoogleDiagnostic() already checks one
 * at a time (module registration, Client ID) - collapsed here into one
 * synchronous yes/no so a screen can hide the action entirely instead of
 * showing a button that always fails.
 *
 * `@react-native-google-signin/google-signin` is a native-only module
 * (TurboModuleRegistry/NativeModules, confirmed via
 * googleModuleDiagnostics.ts) - it is never registered under
 * react-native-web, so this is unconditionally false on Web regardless of
 * Client ID configuration. A real Web-capable Google Sign-In needs a
 * different implementation entirely (Google's own Identity Services JS
 * SDK, or expo-auth-session against Google's OAuth endpoint) - not built
 * here; see the completion report for why this was disclosed rather than
 * attempted.
 */
export function isGoogleSignInAvailable(): boolean {
  if (Platform.OS === 'web') {
    return false;
  }

  const moduleStatus = checkGoogleSigninModuleStatus();
  if (!moduleStatus.turboModuleFound && !moduleStatus.legacyBridgeModuleFound) {
    return false;
  }

  return !!getGoogleWebClientId();
}

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

export type GoogleSignInDiagnosticStep = {
  step: string;
  status: 'ok' | 'error' | 'skipped';
  detail: string;
};

export type GoogleSignInDiagnosticResult =
  | { ok: true; idToken: string; steps: GoogleSignInDiagnosticStep[] }
  | { ok: false; cancelled: boolean; steps: GoogleSignInDiagnosticStep[] };

function describeThrown(error: unknown): string {
  if (error instanceof Error) {
    const code = (error as Error & { code?: unknown }).code;
    return `message="${error.message}"${code !== undefined ? ` code="${String(code)}"` : ''}`;
  }
  return `thrown non-Error value: ${String(error)}`;
}

/**
 * StageArt Google認証 段階的診断 (2026-08-23): runs the exact same
 * configure/hasPlayServices/signIn sequence as signInWithGoogle() above,
 * but never swallows a native error into a generic message - every
 * stage's outcome (including the two non-throwing module-registration
 * probes) is recorded into `steps`, and any thrown error's real
 * `.message`/`.code` (the native module's own error, e.g. from
 * `statusCodes` - see the package's errorCodes.ts) is preserved verbatim
 * rather than being replaced by a fixed StageArt-authored string. This
 * exists specifically to tell "native module isn't registered" apart
 * from "OAuth Client ID/consent screen misconfiguration" apart from "the
 * native SDK call itself failed for some other reason" - the three
 * failure classes a bare `catch { setErrorMessage('...') }` cannot
 * distinguish. Not used for the app's normal, already-working sign-in
 * path (signInWithGoogle() above still is) - only for the diagnostic
 * button-press path while this is under investigation.
 */
export async function signInWithGoogleDiagnostic(): Promise<GoogleSignInDiagnosticResult> {
  const steps: GoogleSignInDiagnosticStep[] = [];

  const moduleStatus = checkGoogleSigninModuleStatus();
  steps.push({
    step: "TurboModuleRegistry.get('RNGoogleSignin')",
    status: moduleStatus.turboModuleFound ? 'ok' : 'error',
    detail: moduleStatus.turboModuleFound ? 'found' : 'not found',
  });
  steps.push({
    step: 'NativeModules.RNGoogleSignin',
    status: moduleStatus.legacyBridgeModuleFound ? 'ok' : 'error',
    detail: moduleStatus.legacyBridgeModuleFound ? 'found' : 'not found',
  });

  if (!moduleStatus.turboModuleFound && !moduleStatus.legacyBridgeModuleFound) {
    return { ok: false, cancelled: false, steps };
  }

  const webClientId = getGoogleWebClientId();
  if (!webClientId) {
    steps.push({ step: 'EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID', status: 'error', detail: 'empty/not configured' });
    return { ok: false, cancelled: false, steps };
  }

  const { GoogleSignin } = await import('@react-native-google-signin/google-signin');

  try {
    GoogleSignin.configure({ webClientId });
    steps.push({ step: 'GoogleSignin.configure()', status: 'ok', detail: `webClientId="${webClientId}"` });
  } catch (error) {
    steps.push({ step: 'GoogleSignin.configure()', status: 'error', detail: describeThrown(error) });
    return { ok: false, cancelled: false, steps };
  }

  try {
    await GoogleSignin.hasPlayServices({ showPlayServicesUpdateDialog: true });
    steps.push({ step: 'GoogleSignin.hasPlayServices()', status: 'ok', detail: 'succeeded' });
  } catch (error) {
    steps.push({ step: 'GoogleSignin.hasPlayServices()', status: 'error', detail: describeThrown(error) });
    return { ok: false, cancelled: false, steps };
  }

  try {
    const response = await GoogleSignin.signIn();

    if (response.type === 'cancelled') {
      steps.push({ step: 'GoogleSignin.signIn()', status: 'skipped', detail: 'user cancelled the picker' });
      return { ok: false, cancelled: true, steps };
    }

    const idToken = response.data.idToken;
    if (!idToken) {
      steps.push({ step: 'GoogleSignin.signIn()', status: 'error', detail: 'succeeded but response had no idToken' });
      return { ok: false, cancelled: false, steps };
    }

    steps.push({ step: 'GoogleSignin.signIn()', status: 'ok', detail: 'succeeded, idToken received' });
    return { ok: true, idToken, steps };
  } catch (error) {
    steps.push({ step: 'GoogleSignin.signIn()', status: 'error', detail: describeThrown(error) });
    return { ok: false, cancelled: false, steps };
  }
}
