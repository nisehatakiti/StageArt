import type { ExpoConfig } from 'expo/config';

const GOOGLE_WEB_CLIENT_ID = process.env.EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID ?? '';
const GOOGLE_IOS_URL_SCHEME = process.env.EXPO_PUBLIC_GOOGLE_IOS_URL_SCHEME ?? '';
const googleSignInPlugin: [string, { iosUrlScheme: string }] = ['@react-native-google-signin/google-signin', { iosUrlScheme: GOOGLE_IOS_URL_SCHEME }];
const DEV_API_BASE_URL = 'https://dev-stageart.hatakiti.com/stageart-test/wp-json/stageart/v1';
const WEB_EXPORT_BASE_URL = process.env.STAGEART_WEB_EXPORT_BASE_URL;
const apiEnv = process.env.EAS_BUILD_PROFILE ?? 'development';
function resolveApiBaseUrl(env: string): string {
  if (env === 'development') return DEV_API_BASE_URL;
  console.warn(`[app.config.ts] No real "${env}" API URL exists yet. Falling back to the Development backend.`);
  return DEV_API_BASE_URL;
}
const config: ExpoConfig = {
  name: 'StageArt', slug: 'stageart', version: '1.0.0', orientation: 'portrait',
  icon: './assets/images/icon.png', scheme: 'stageart', userInterfaceStyle: 'automatic',
  ios: { bundleIdentifier: 'com.hatakiti.stageart' },
  android: { package: 'com.hatakiti.stageart', adaptiveIcon: { backgroundColor: '#050505', foregroundImage: './assets/images/android-icon-foreground.png', backgroundImage: './assets/images/android-icon-background.png', monochromeImage: './assets/images/android-icon-monochrome.png' }, predictiveBackGestureEnabled: false },
  web: { output: 'static', favicon: './assets/images/stageart-icon.svg' },
  plugins: ['expo-router', ['expo-splash-screen', { backgroundColor: '#050505', image: './assets/images/splash-icon.png', imageWidth: 76 }], 'expo-secure-store', 'expo-sharing', ...(GOOGLE_IOS_URL_SCHEME ? [googleSignInPlugin] : [])],
  experiments: { typedRoutes: true, reactCompiler: true, ...(WEB_EXPORT_BASE_URL ? { baseUrl: WEB_EXPORT_BASE_URL } : {}) },
  extra: { apiEnv, apiBaseUrl: resolveApiBaseUrl(apiEnv), googleWebClientId: GOOGLE_WEB_CLIENT_ID, eas: { projectId: 'a3c6b296-a4d9-4f6e-84ee-3852f1025f9b' } },
};
export default config;
