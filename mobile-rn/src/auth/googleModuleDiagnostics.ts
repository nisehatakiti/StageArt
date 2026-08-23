import { NativeModules, TurboModuleRegistry } from 'react-native';

export type GoogleSigninModuleStatus = {
  turboModuleFound: boolean;
  legacyBridgeModuleFound: boolean;
  otherGoogleRelatedKeys: string[];
};

/**
 * StageArt Google認証 診断 (2026-08-23): a minimal, non-throwing probe of
 * whether the native RNGoogleSignin module is actually resolvable from
 * JS, run *before* ever importing the
 * @react-native-google-signin/google-signin package itself - that
 * package's own NativeGoogleSignin.js calls
 * `TurboModuleRegistry.getEnforcing('RNGoogleSignin')` at module top
 * level (confirmed by reading its source), which throws the exact
 * `'RNGoogleSignin' could not be found...` invariant the moment the
 * module is evaluated. `TurboModuleRegistry.get()` (used here) performs
 * the identical lookup - Fabric/New Architecture's `__turboModuleProxy`
 * first, legacy `NativeModules` bridge as fallback (see
 * react-native/Libraries/TurboModule/TurboModuleRegistry.js's own
 * `requireModule()`) - but never throws, so this is safe to call
 * unconditionally to find out *which* layer is missing the module,
 * rather than only learning "it's missing" after a crash.
 */
export function checkGoogleSigninModuleStatus(): GoogleSigninModuleStatus {
  const turboModuleFound = TurboModuleRegistry.get('RNGoogleSignin') != null;
  const legacyBridgeModuleFound = NativeModules.RNGoogleSignin != null;
  const otherGoogleRelatedKeys = Object.keys(NativeModules).filter((key) => /google/i.test(key));

  return { turboModuleFound, legacyBridgeModuleFound, otherGoogleRelatedKeys };
}

export function describeGoogleSigninModuleStatus(status: GoogleSigninModuleStatus): string {
  return (
    `RNGoogleSignin diagnostics — ` +
    `TurboModuleRegistry: ${status.turboModuleFound ? 'found' : 'NOT FOUND'}, ` +
    `legacy NativeModules bridge: ${status.legacyBridgeModuleFound ? 'found' : 'NOT FOUND'}, ` +
    `other Google-related NativeModules keys: [${status.otherGoogleRelatedKeys.join(', ') || 'none'}]`
  );
}
