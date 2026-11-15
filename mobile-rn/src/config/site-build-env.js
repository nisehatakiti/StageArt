// StageArt public-site / application build separation: the Single Source
// of Truth for which kind of Web build `EXPO_PUBLIC_STAGEART_SITE` asks for.
//
// A plain CommonJS .js file for the same reason as src/api/environment.js:
// app.config.ts can only load sibling .js files, and Jest loads it the
// same way. site-build-env.d.ts gives .ts importers full types.
//
// - "app" (or unset/empty): the application build for dev.stageart.top /
//   app.stageart.top, and every native build. Unchanged behavior.
// - "public": the public-site build for stageart.top / www.stageart.top /
//   dummy.stageart.top. Authentication screens render no UI at all (not
//   even in the exported HTML) - see src/components/app-only-route.tsx.
//
// Anything else throws, and "public" is refused for an EAS (native)
// build: a typo must never silently produce the wrong kind of site.
const SITE_BUILD_APP = 'app';
const SITE_BUILD_PUBLIC = 'public';

function resolveSiteBuild(value, options = {}) {
  const site = value === undefined || value === '' ? SITE_BUILD_APP : value;

  if (site !== SITE_BUILD_APP && site !== SITE_BUILD_PUBLIC) {
    throw new Error(`Unsupported EXPO_PUBLIC_STAGEART_SITE: ${value} (expected "public", "app" or unset)`);
  }

  if (site === SITE_BUILD_PUBLIC && options.easBuildPlatform) {
    throw new Error(
      `EXPO_PUBLIC_STAGEART_SITE=public is a Web-only public-site build and must not be used for an EAS ${options.easBuildPlatform} build`
    );
  }

  return site;
}

module.exports = { SITE_BUILD_APP, SITE_BUILD_PUBLIC, resolveSiteBuild };
