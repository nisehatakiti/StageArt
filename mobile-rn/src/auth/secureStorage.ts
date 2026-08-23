import * as SecureStore from 'expo-secure-store';

/**
 * StageArt Authentication Phase 5: replaces the earlier WordPress
 * Application Password storage (username + Application Password) with
 * the StageArt Access/Refresh Token pair returned by /auth/google and
 * /auth/email/*. Still backed by Expo SecureStore (iOS Keychain /
 * Android Keystore), never AsyncStorage or a plain file - see this
 * Phase's report for the explicit "no plaintext token storage"
 * requirement. The Access Token is short-lived (1h) and is not strictly
 * required to persist across a full app restart (a stored Refresh Token
 * alone is enough to obtain a fresh one via AuthContext's boot-time
 * restore), but caching it here still saves one avoidable /auth/refresh
 * round-trip on every cold start.
 */
const ACCESS_TOKEN_KEY = 'stageart_access_token';
const REFRESH_TOKEN_KEY = 'stageart_refresh_token';

export type StoredTokens = { accessToken: string; refreshToken: string };

export async function loadStoredTokens(): Promise<StoredTokens | null> {
  const [accessToken, refreshToken] = await Promise.all([
    SecureStore.getItemAsync(ACCESS_TOKEN_KEY),
    SecureStore.getItemAsync(REFRESH_TOKEN_KEY),
  ]);

  // A Refresh Token without an Access Token is still a usable session
  // (AuthContext exchanges it via /auth/refresh); an Access Token alone,
  // without a Refresh Token to fall back on once it expires, is not.
  if (!refreshToken) {
    return null;
  }

  return { accessToken: accessToken ?? '', refreshToken };
}

export async function saveTokens(tokens: StoredTokens): Promise<void> {
  await Promise.all([
    SecureStore.setItemAsync(ACCESS_TOKEN_KEY, tokens.accessToken),
    SecureStore.setItemAsync(REFRESH_TOKEN_KEY, tokens.refreshToken),
  ]);
}

export async function saveAccessToken(accessToken: string): Promise<void> {
  await SecureStore.setItemAsync(ACCESS_TOKEN_KEY, accessToken);
}

export async function clearTokens(): Promise<void> {
  await Promise.all([SecureStore.deleteItemAsync(ACCESS_TOKEN_KEY), SecureStore.deleteItemAsync(REFRESH_TOKEN_KEY)]);
}
