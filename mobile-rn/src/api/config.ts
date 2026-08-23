import Constants from 'expo-constants';

/**
 * Reads the Base URL resolved by app.config.ts's `extra` block (see that
 * file for the Environment Strategy - only "development" points at a
 * real backend today).
 */
export function getApiBaseUrl(): string {
  const apiBaseUrl = Constants.expoConfig?.extra?.apiBaseUrl;

  if (typeof apiBaseUrl !== 'string' || apiBaseUrl.length === 0) {
    throw new Error('apiBaseUrl is not configured (see app.config.ts).');
  }

  return apiBaseUrl;
}

export function getApiEnv(): string {
  const apiEnv = Constants.expoConfig?.extra?.apiEnv;

  return typeof apiEnv === 'string' && apiEnv.length > 0 ? apiEnv : 'development';
}

/**
 * '' until the Google Cloud OAuth Client IDs exist (see app.config.ts) -
 * src/auth/googleSignIn.ts treats an empty value as "Google Sign-In is
 * not configured yet" rather than attempting to configure the native SDK
 * with a blank Client ID.
 */
export function getGoogleWebClientId(): string {
  const googleWebClientId = Constants.expoConfig?.extra?.googleWebClientId;

  return typeof googleWebClientId === 'string' ? googleWebClientId : '';
}
