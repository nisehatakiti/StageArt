import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

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
 *
 * StageArt Web First Phase 1: `expo-secure-store`'s own web platform
 * module (`ExpoSecureStore.web.ts`) is a literal empty stub - confirmed
 * by reading the package's source, not assumed - so calling
 * `SecureStore.getItemAsync()` etc. on web throws
 * `TypeError: ... is not a function` at runtime, not a graceful no-op.
 * Web falls back to `window.localStorage` instead. This is a disclosed,
 * standard-but-imperfect tradeoff: localStorage is JS-readable (XSS
 * surface) in a way Keychain/Keystore is not; an httpOnly-cookie-based
 * session would be stronger but needs Backend support that does not
 * exist today. Native platforms are completely unaffected - this branch
 * only ever executes when `Platform.OS === 'web'`.
 */
const ACCESS_TOKEN_KEY = 'stageart_access_token';
const REFRESH_TOKEN_KEY = 'stageart_refresh_token';

const isWeb = Platform.OS === 'web';

async function getItem(key: string): Promise<string | null> {
  return isWeb ? (globalThis.localStorage?.getItem(key) ?? null) : SecureStore.getItemAsync(key);
}

async function setItem(key: string, value: string): Promise<void> {
  if (isWeb) {
    globalThis.localStorage?.setItem(key, value);
    return;
  }
  await SecureStore.setItemAsync(key, value);
}

async function deleteItem(key: string): Promise<void> {
  if (isWeb) {
    globalThis.localStorage?.removeItem(key);
    return;
  }
  await SecureStore.deleteItemAsync(key);
}

export type StoredTokens = { accessToken: string; refreshToken: string };

export async function loadStoredTokens(): Promise<StoredTokens | null> {
  const [accessToken, refreshToken] = await Promise.all([getItem(ACCESS_TOKEN_KEY), getItem(REFRESH_TOKEN_KEY)]);

  // A Refresh Token without an Access Token is still a usable session
  // (AuthContext exchanges it via /auth/refresh); an Access Token alone,
  // without a Refresh Token to fall back on once it expires, is not.
  if (!refreshToken) {
    return null;
  }

  return { accessToken: accessToken ?? '', refreshToken };
}

export async function saveTokens(tokens: StoredTokens): Promise<void> {
  await Promise.all([setItem(ACCESS_TOKEN_KEY, tokens.accessToken), setItem(REFRESH_TOKEN_KEY, tokens.refreshToken)]);
}

export async function saveAccessToken(accessToken: string): Promise<void> {
  await setItem(ACCESS_TOKEN_KEY, accessToken);
}

export async function clearTokens(): Promise<void> {
  await Promise.all([deleteItem(ACCESS_TOKEN_KEY), deleteItem(REFRESH_TOKEN_KEY)]);
}
