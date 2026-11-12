import { getGoogleWebClientId } from '@/api/config';

/**
 * StageArt Google Web Sign-In (2026-09-04): the Web sibling of
 * googleSignIn.ts (Metro/Expo picks this file over the native one when
 * bundling for Web - the same platform-extension convention already
 * used by confirmAlert.web.tsx). Deliberately not a Platform.OS branch
 * inside the native file: the two implementations share no code at all
 * (`@react-native-google-signin/google-signin` vs Google Identity
 * Services, an entirely different SDK - see GoogleSignInButtonWeb.tsx),
 * and this keeps googleSignIn.ts (native, already working) completely
 * untouched.
 *
 * isGoogleSignInAvailable() only needs a configured Web Client ID here -
 * there is no native-module registration concept on Web at all (the
 * TurboModuleRegistry/NativeModules checks native's version does are
 * meaningless on Web and were the reason isGoogleSignInAvailable()
 * previously returned an unconditional `false` for Platform.OS==='web'
 * regardless of configuration).
 */
export function isGoogleSignInAvailable(): boolean {
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

export type GoogleSignInDiagnosticStep = {
  step: string;
  status: 'ok' | 'error' | 'skipped';
  detail: string;
};

export type GoogleSignInDiagnosticResult =
  | { ok: true; idToken: string; steps: GoogleSignInDiagnosticStep[] }
  | { ok: false; cancelled: boolean; steps: GoogleSignInDiagnosticStep[] };

/**
 * Google Identity Services only ever issues an ID Token through ITS OWN
 * rendered button's callback (see GoogleSignInButtonWeb.tsx) - never
 * through a function this app calls after its own button is pressed, as
 * native's imperative GoogleSignin.signIn() does. These two exports
 * exist only so login.tsx's shared import statement (unchanged across
 * platforms) still resolves and type-checks; login.tsx's Web branch
 * never actually calls either of them.
 */
export async function signInWithGoogle(): Promise<string> {
  throw new Error('signInWithGoogle() is not supported on Web - see GoogleSignInButtonWeb.tsx.');
}

export async function signInWithGoogleDiagnostic(): Promise<GoogleSignInDiagnosticResult> {
  return {
    ok: false,
    cancelled: false,
    steps: [
      {
        step: 'signInWithGoogleDiagnostic',
        status: 'skipped',
        detail: 'Web uses GoogleSignInButtonWeb (Google Identity Services), not this function.',
      },
    ],
  };
}
