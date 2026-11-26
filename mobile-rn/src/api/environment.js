// StageArt Development/Production API separation: the Single Source of
// Truth for which backend a given `EAS_BUILD_PROFILE` value talks to.
//
// Deliberately a plain CommonJS .js file, not .ts: app.config.ts is
// loaded by Expo's own config loader (@expo/require-utils), which
// transpiles and _compile()s only that one entry file - it does not
// register a require.extensions['.ts'] hook, so a sibling .ts file
// imported by relative path (`require('./src/api/environment')`) fails
// with "Cannot find module" the instant app.config.ts is evaluated (a
// real, confirmed failure - not a hypothetical). A plain .js file needs
// no such hook and resolves via Node's ordinary CommonJS rules, so both
// app.config.ts (transpiled to CommonJS) and Jest (via Babel's CJS
// interop) can load it identically. environment.d.ts alongside this
// file gives every .ts importer (environment.test.ts) full static
// types without needing this file itself to be TypeScript.
//
// Exactly two recognized environments exist: "development" and
// "production". Anything else - a typo, an unconfigured CI profile,
// `undefined`, an empty string, a future EAS profile nobody has wired
// up yet (eas.json's own "preview" included) - throws rather than
// silently resolving to either backend. The one failure mode this
// exists to make structurally impossible is "a misconfigured
// Production build silently talks to the Development API" - a fallback
// of any kind (Dev *or* Prod) for an unrecognized value would still
// allow that class of bug to hide instead of failing loudly at config
// time.
const DEV_API_BASE_URL = 'https://dev-api.stageart.top/wp-json/stageart/v1';
const PROD_API_BASE_URL = 'https://api.stageart.top/wp-json/stageart/v1';

function resolveApiBaseUrl(env) {
  if (env === 'development') return DEV_API_BASE_URL;
  if (env === 'production') return PROD_API_BASE_URL;
  throw new Error(`Unsupported StageArt environment: ${env}`);
}

module.exports = { DEV_API_BASE_URL, PROD_API_BASE_URL, resolveApiBaseUrl };
