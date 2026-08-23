import type { ExpoConfig } from 'expo/config';

/**
 * StageArt Mobile Phase 5.1: Environment Strategy (see the Phase 5.0/5.1
 * reports). Only one real backend exists today - dev-stageart.hatakiti.com.
 * Staging and Production URLs are deliberately NOT invented here; EAS_BUILD_PROFILE
 * selects a named environment, and any profile other than "development"
 * currently falls back to the same dev URL with a loud console warning
 * rather than silently pointing at a URL nobody has configured yet.
 *
 * `ios.bundleIdentifier` / `android.package`: explicitly decided by the
 * user (not invented here) as "com.hatakiti.stageart" for both
 * platforms - see the EAS Development Build Identifier Setup Phase.
 *
 * EAS Environment Setup Phase: connected to the existing EAS Project
 * (@stageart/stageart, extra.eas.projectId below) via `eas init --id`.
 * `slug` was changed from the earlier placeholder 'stageart-mobile' to
 * 'stageart' because that is the slug the EAS Project was actually
 * created with on the Expo Dashboard - `eas project:info` refuses to
 * resolve the project otherwise (slug must match the registered
 * project). This is reconciling local config with an already-created
 * remote project, not a new branding decision - see this Phase's
 * report for the discrepancy as found.
 *
 * Authentication Phase 5 (Google Sign-In): GOOGLE_WEB_CLIENT_ID and
 * GOOGLE_IOS_URL_SCHEME are read from EXPO_PUBLIC_* env vars, not
 * hardcoded - the Google Cloud OAuth Client IDs have not been created
 * yet (see the Backend Phase 2 report's separate Google Cloud Console
 * setup guide), so both default to '' here. A Web Client ID is not a
 * secret (it appears in every Google ID Token's own `aud` claim, and the
 * Backend already validates it there too), so EXPO_PUBLIC_ exposure is
 * correct, not a leak. Until real values are set and a new EAS
 * Development Build is produced, the Google Sign-In native module is
 * present in the JS bundle but not configured with working Client IDs -
 * see src/auth/googleSignIn.ts for how that is kept from breaking
 * anything else.
 *
 * The @react-native-google-signin/google-signin config plugin itself
 * (unlike the JS module) does NOT degrade gracefully on an empty
 * iosUrlScheme - it throws during config resolution ("Missing
 * `iosUrlScheme` in provided options"), which breaks `expo config`,
 * `expo-doctor`, and `expo export` entirely, not just the Google flow
 * (confirmed by running `expo-doctor` with GOOGLE_IOS_URL_SCHEME unset -
 * see this Phase's report). The plugin entry is therefore only added to
 * `plugins` once a real iosUrlScheme is configured; until then the app
 * builds and runs exactly as if the plugin were not installed at all.
 */

const GOOGLE_WEB_CLIENT_ID = process.env.EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID ?? '';
const GOOGLE_IOS_URL_SCHEME = process.env.EXPO_PUBLIC_GOOGLE_IOS_URL_SCHEME ?? '';

const googleSignInPlugin: [string, { iosUrlScheme: string }] = [
  '@react-native-google-signin/google-signin',
  { iosUrlScheme: GOOGLE_IOS_URL_SCHEME },
];

const DEV_API_BASE_URL = 'https://dev-stageart.hatakiti.com/stageart-test/wp-json/stageart/v1';

/**
 * StageArt Authentication Phase 6: only set for the one-off
 * `expo export --platform web` build that gets deployed to
 * https://dev-stageart.hatakiti.com/verify-app/ (a static subpath, not
 * the site root) as the HTTPS email-confirmation landing page. Unset in
 * every other context (native builds, `expo start`, the EAS Development
 * Build), so this has zero effect on the app itself - the static export
 * is a separate artifact from the app, reusing verify-email.tsx's code
 * but never installed on a device.
 */
const WEB_EXPORT_BASE_URL = process.env.STAGEART_WEB_EXPORT_BASE_URL;

const apiEnv = process.env.EAS_BUILD_PROFILE ?? 'development';

function resolveApiBaseUrl(env: string): string {
  if (env === 'development') {
    return DEV_API_BASE_URL;
  }

  // eslint-disable-next-line no-console
  console.warn(
    `[app.config.ts] No real "${env}" API URL exists yet (see Phase 5.1 Open Issues). ` +
      'Falling back to the Development backend so the app remains usable.'
  );

  return DEV_API_BASE_URL;
}

const config: ExpoConfig = {
  name: 'StageArt',
  slug: 'stageart',
  version: '1.0.0',
  orientation: 'portrait',
  icon: './assets/images/icon.png',
  scheme: 'stageart',
  userInterfaceStyle: 'automatic',
  ios: {
    icon: './assets/expo.icon',
    bundleIdentifier: 'com.hatakiti.stageart',
  },
  android: {
    package: 'com.hatakiti.stageart',
    adaptiveIcon: {
      backgroundColor: '#E6F4FE',
      foregroundImage: './assets/images/android-icon-foreground.png',
      backgroundImage: './assets/images/android-icon-background.png',
      monochromeImage: './assets/images/android-icon-monochrome.png',
    },
    predictiveBackGestureEnabled: false,
  },
  web: {
    output: 'static',
    favicon: './assets/images/favicon.png',
  },
  plugins: [
    'expo-router',
    [
      'expo-splash-screen',
      {
        backgroundColor: '#208AEF',
        image: './assets/images/splash-icon.png',
        imageWidth: 76,
      },
    ],
    'expo-secure-store',
    'expo-sharing',
    ...(GOOGLE_IOS_URL_SCHEME ? [googleSignInPlugin] : []),
  ],
  experiments: {
    typedRoutes: true,
    reactCompiler: true,
    ...(WEB_EXPORT_BASE_URL ? { baseUrl: WEB_EXPORT_BASE_URL } : {}),
  },
  extra: {
    apiEnv,
    apiBaseUrl: resolveApiBaseUrl(apiEnv),
    googleWebClientId: GOOGLE_WEB_CLIENT_ID,
    eas: {
      projectId: 'a3c6b296-a4d9-4f6e-84ee-3852f1025f9b',
    },
  },
};

export default config;
