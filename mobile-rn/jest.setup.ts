// React 19's stricter act()-environment detection needs this flag set
// explicitly under Jest; without it, state updates inside
// @testing-library/react-native's renderHook() (used by
// src/auth/AuthContext.test.tsx) log "not configured to support act()"
// and the hook's return value is never populated.
// eslint-disable-next-line @typescript-eslint/no-explicit-any
(global as any).IS_REACT_ACT_ENVIRONMENT = true;

/**
 * Jest never runs Expo's config-resolution step, so `expo-constants`'s
 * `Constants.expoConfig` is empty under test even though app.config.ts
 * defines a real `extra.apiBaseUrl` at build/runtime. This mock keeps
 * src/api/config.ts's real (non-test) code path exercised as-is,
 * instead of special-casing test environments inside application code.
 */
jest.mock('expo-constants', () => ({
  __esModule: true,
  default: {
    expoConfig: {
      extra: {
        apiEnv: 'development',
        apiBaseUrl: 'https://dev-stageart.hatakiti.com/stageart-test/wp-json/stageart/v1',
      },
    },
  },
}));
