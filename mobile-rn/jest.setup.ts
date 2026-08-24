/**
 * StageArt Web First Phase 1: every renderRouter() test transitively
 * loads the whole src/app directory (Expo Router resolves the full
 * route table regardless of `initialUrl`), so index.tsx's own import of
 * StartupAnimation - and, transitively, react-native-svg (added this
 * Phase for the canonical brand icon/logo) - adds real Babel/Metro
 * transform overhead to every single test file, not just ones that
 * render index.tsx. Under full-suite parallel worker load this tipped
 * several already-marginal tests (schedule-to-detail,
 * index-boot-authenticated, home-logout - confirmed by re-running the
 * full suite twice and seeing a different, overlapping subset fail each
 * time, and each passing cleanly in isolation) over Jest's 5000ms
 * default per-test timeout. Raised, not the underlying test logic.
 */
jest.setTimeout(15000);

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
