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
// Exactly three recognized environments exist: "development",
// "production" and "verification". Anything else - a typo, an
// unconfigured CI profile, `undefined`, an empty string, a future EAS
// profile nobody has wired up yet (eas.json's own "preview" included) -
// throws rather than silently resolving to any backend. The one failure
// mode this exists to make structurally impossible is "a misconfigured
// Production build silently talks to the Development API" - a fallback
// of any kind (Dev *or* Prod) for an unrecognized value would still
// allow that class of bug to hide instead of failing loudly at config
// time.
//
// "verification" (PR #7 R14-B stage 11, test/r14b-verification-environment)
// is the isolated R14-B verification environment (verify-api.stageart.top /
// verify.stageart.top), reached ONLY when it is named explicitly
// (EAS_BUILD_PROFILE=verification, e.g. via
// `export-web-site.js --api verification`). It is never a default or a
// fallback, and no eas.json profile uses it.
const DEV_API_BASE_URL = 'https://dev-api.stageart.top/wp-json/stageart/v1';
const PROD_API_BASE_URL = 'https://api.stageart.top/wp-json/stageart/v1';
const VERIFICATION_API_BASE_URL = 'https://verify-api.stageart.top/wp-json/stageart/v1';

/** Every recognized environment and its API base URL. */
const API_BASE_URLS = Object.freeze({
  development: DEV_API_BASE_URL,
  production: PROD_API_BASE_URL,
  verification: VERIFICATION_API_BASE_URL,
});

function resolveApiBaseUrl(env) {
  if (env === 'development') return DEV_API_BASE_URL;
  if (env === 'production') return PROD_API_BASE_URL;
  if (env === 'verification') return VERIFICATION_API_BASE_URL;
  throw new Error(`Unsupported StageArt environment: ${env}`);
}

// The public Web origin (public organization / production pages) of each
// environment. development / production are the values
// src/api/config.ts has always used (dummy.stageart.top / stageart.top);
// verification has its own, verify.stageart.top. Resolved here at config
// time (app.config.ts -> extra.publicWebBaseUrl) so that only the chosen
// environment's origin is embedded in a build.
const DEV_PUBLIC_WEB_BASE_URL = 'https://dummy.stageart.top';
const PROD_PUBLIC_WEB_BASE_URL = 'https://stageart.top';
const VERIFICATION_PUBLIC_WEB_BASE_URL = 'https://verify.stageart.top';

const PUBLIC_WEB_BASE_URLS = Object.freeze({
  development: DEV_PUBLIC_WEB_BASE_URL,
  production: PROD_PUBLIC_WEB_BASE_URL,
  verification: VERIFICATION_PUBLIC_WEB_BASE_URL,
});

function resolvePublicWebBaseUrl(env) {
  if (env === 'development') return DEV_PUBLIC_WEB_BASE_URL;
  if (env === 'production') return PROD_PUBLIC_WEB_BASE_URL;
  if (env === 'verification') return VERIFICATION_PUBLIC_WEB_BASE_URL;
  throw new Error(`Unsupported StageArt environment: ${env}`);
}

module.exports = {
  DEV_API_BASE_URL,
  PROD_API_BASE_URL,
  VERIFICATION_API_BASE_URL,
  API_BASE_URLS,
  resolveApiBaseUrl,
  VERIFICATION_PUBLIC_WEB_BASE_URL,
  PUBLIC_WEB_BASE_URLS,
  resolvePublicWebBaseUrl,
};
