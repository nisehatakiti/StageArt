import type { ExpoConfig } from 'expo/config';

import { resolveApiBaseUrl } from './src/api/environment';

const GOOGLE_WEB_CLIENT_ID = process.env.EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID ?? '';
const GOOGLE_IOS_URL_SCHEME = process.env.EXPO_PUBLIC_GOOGLE_IOS_URL_SCHEME ?? '';
const googleSignInPlugin: [string, { iosUrlScheme: string }] = ['@react-native-google-signin/google-signin', { iosUrlScheme: GOOGLE_IOS_URL_SCHEME }];
const WEB_EXPORT_BASE_URL = process.env.STAGEART_WEB_EXPORT_BASE_URL;
// No EAS_BUILD_PROFILE at all (a plain local `expo start`/`export:web`,
// not an EAS build) defaults to "development" - a genuinely different
// case from an unrecognized *value*, which src/api/environment.js's
// resolveApiBaseUrl() deliberately refuses to guess about (see its own
// docblock).
const apiEnv = process.env.EAS_BUILD_PROFILE ?? 'development';
const config: ExpoConfig = {
  name: 'StageArt', slug: 'stageart', version: '1.0.0', orientation: 'portrait',
  icon: './assets/images/icon.png', scheme: 'stageart', userInterfaceStyle: 'automatic',
  ios: { bundleIdentifier: 'com.hatakiti.stageart' },
  android: { package: 'com.hatakiti.stageart', adaptiveIcon: { backgroundColor: '#050505', foregroundImage: './assets/images/android-icon-foreground.png', monochromeImage: './assets/images/android-icon-monochrome.png' }, predictiveBackGestureEnabled: false },
  web: { output: 'static', favicon: './assets/images/stageart-icon-favicon.png' },
  plugins: ['expo-router', ['expo-splash-screen', { backgroundColor: '#050505', image: './assets/images/icon.png', imageWidth: 76 }], 'expo-secure-store', 'expo-sharing', ...(GOOGLE_IOS_URL_SCHEME ? [googleSignInPlugin] : [])],
  experiments: { typedRoutes: true, reactCompiler: true, ...(WEB_EXPORT_BASE_URL ? { baseUrl: WEB_EXPORT_BASE_URL } : {}) },
  extra: { apiEnv, apiBaseUrl: resolveApiBaseUrl(apiEnv), googleWebClientId: GOOGLE_WEB_CLIENT_ID, eas: { projectId: 'a3c6b296-a4d9-4f6e-84ee-3852f1025f9b' } },
};
export default config;
