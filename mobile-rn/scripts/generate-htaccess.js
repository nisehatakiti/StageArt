#!/usr/bin/env node
/**
 * StageArt Web版 デプロイ整備 Phase: generates `dist/.htaccess` for Expo
 * Router's static web export (`expo export --platform web`) from the
 * actual exported file list - never a hand-maintained, hardcoded rule
 * set. `expo export` itself does not produce an `.htaccess` at all (Expo
 * is host-agnostic); this project's own deploy target (Apache, behind
 * ConoHa WING's nginx cache layer) needs one because Expo Router's
 * static export writes one literal file per route, and a route with a
 * dynamic segment (e.g. `/organizations/[id]/edit`) is written to a file
 * whose name still contains the literal bracket
 * (`organizations/[id]/edit.html`) - a real browser request for the
 * *actual* id (`/organizations/abc123/edit`) needs an explicit rewrite
 * to reach it.
 *
 * Run via `npm run export:web` (which runs this automatically via the
 * `postexport:web` npm lifecycle hook) - never hand-edit `dist/.htaccess`
 * directly, since the next export would silently discard that edit.
 *
 * Design (kept deliberately simple/explicit over clever, per this
 * project's own "don't over-abstract" convention):
 * - One explicit RewriteRule per actual route found under `dist/`,
 *   translating each literal `[bracket]` path segment into an Apache
 *   capture group `([^/]+)`, sourced from the real file list rather than
 *   any route registry this script would otherwise have to keep in sync
 *   by hand.
 * - Ordered most-specific first: more path segments before fewer, and
 *   (at equal segment count) fewer bracket segments before more. This is
 *   what lets `/organizations` (a real, literal application route) take
 *   priority over the generic single-segment Organization-public-page
 *   catch-all (`/{organizationSlug}`) that a route like
 *   `[organizationSlug]/index.tsx` produces - both are one path segment,
 *   but the literal route has zero bracket segments and the catch-all
 *   has one, so the literal route is tried first. The same reasoning
 *   keeps `/o/[organizationSlug]` (the legacy redirect) ahead of the
 *   generic two-segment `/{organizationSlug}/{productionSlug}` catch-all.
 * - `index.html` files map to their *parent directory's own* clean URL
 *   (`organizations/index.html` -> `/organizations`), except the
 *   top-level `dist/index.html` itself, which is left to Apache's own
 *   default DocumentRoot index resolution (already working in
 *   production today - no rewrite needed or generated for it).
 * - A leading "already a real file on disk -> serve it as-is" rule
 *   (unchanged from this project's previous hand-written `.htaccess`)
 *   short-circuits every genuine static asset (`_expo/`, `assets/`,
 *   `favicon.ico`) and any request that already names a real `.html`
 *   file directly, before any of the generated rules below it run.
 */

const fs = require('fs');
const path = require('path');

// Defaults to `dist/` (the `expo export --platform web` default), but
// accepts an override so this can also validate an `--output-dir`-driven
// export without clobbering `dist/` itself.
const DIST_DIR = path.resolve(__dirname, '..', process.argv[2] || 'dist');
const OUTPUT_PATH = path.join(DIST_DIR, '.htaccess');

/** Files Expo's static export writes that are not themselves an
 * application route this generator should produce a RewriteRule for. */
const EXCLUDED_FILENAMES = new Set(['_sitemap.html', '+not-found.html']);

/**
 * @returns {string[]} every `.html` file under `dist/`, as POSIX-style
 * paths relative to `dist/` (e.g. `organizations/[id]/edit.html`).
 */
function listHtmlFiles(dir, base = '') {
  const entries = fs.readdirSync(dir, { withFileTypes: true });
  let results = [];

  for (const entry of entries) {
    const relativePath = base ? `${base}/${entry.name}` : entry.name;

    if (entry.isDirectory()) {
      results = results.concat(listHtmlFiles(path.join(dir, entry.name), relativePath));
    } else if (entry.isFile() && entry.name.endsWith('.html')) {
      results.push(relativePath);
    }
  }

  return results;
}

/**
 * @param {string} relativeHtmlPath e.g. `organizations/[id]/edit.html`
 * @returns {{ urlPath: string; filePath: string; segmentCount: number; bracketCount: number } | null}
 * `null` for the one case deliberately left ungenerated (the top-level
 * index route - see this file's own docblock).
 */
function toRoute(relativeHtmlPath) {
  const withoutExt = relativeHtmlPath.slice(0, -'.html'.length);
  const isIndex = withoutExt === 'index' || withoutExt.endsWith('/index');
  const urlPath = isIndex ? withoutExt.slice(0, -'index'.length).replace(/\/$/, '') : withoutExt;

  if (urlPath === '') {
    // The top-level `dist/index.html` - Apache's own DocumentRoot index
    // resolution already serves this with no rewrite needed.
    return null;
  }

  const segments = urlPath.split('/');
  const bracketCount = segments.filter((segment) => segment.startsWith('[') && segment.endsWith(']')).length;

  return {
    urlPath,
    filePath: relativeHtmlPath,
    segmentCount: segments.length,
    bracketCount,
  };
}

function toRewritePattern(urlPath) {
  return urlPath
    .split('/')
    .map((segment) => (segment.startsWith('[') && segment.endsWith(']') ? '([^/]+)' : segment))
    .join('/');
}

function generate() {
  if (!fs.existsSync(DIST_DIR)) {
    console.error(`generate-htaccess: ${DIST_DIR} does not exist - run "expo export --platform web" first.`);
    process.exit(1);
  }

  const htmlFiles = listHtmlFiles(DIST_DIR);
  const routes = htmlFiles
    .filter((relativePath) => !EXCLUDED_FILENAMES.has(path.basename(relativePath)))
    .map(toRoute)
    .filter((route) => route !== null);

  // Most-specific first: more path segments before fewer; at equal
  // segment count, fewer bracket segments (more literal, more specific)
  // before more bracket segments (broader catch-all) - see this file's
  // own docblock for why this ordering is load-bearing, not cosmetic.
  routes.sort((a, b) => b.segmentCount - a.segmentCount || a.bracketCount - b.bracketCount);

  const ruleLines = routes.map((route) => {
    const pattern = toRewritePattern(route.urlPath);
    return `RewriteRule ^${pattern}/?$ /${route.filePath} [L]`;
  });

  const content = `# StageArt mobile-rn Web (Expo Router static export) clean-URL routing.
# Dedicated to app.hatakiti.com only - does not touch dev-stageart.hatakiti.com
# or any WordPress .htaccess.
#
# GENERATED FILE - do not hand-edit. Regenerate via \`npm run export:web\`
# (mobile-rn/scripts/generate-htaccess.js reads the actual exported file
# list under dist/ and writes this file fresh every time - see that
# script's own docblock for the full design rationale).
<IfModule mod_rewrite.c>
RewriteEngine On

# Apache re-evaluates a rewritten absolute-path target from the top of
# this same ruleset (standard .htaccess behavior) - once a request has
# already been resolved to a real file (either directly, or by an
# earlier rule below), stop immediately so the rules further down never
# see (and re-match) their own rewrite targets.
RewriteCond %{REQUEST_FILENAME} -f
RewriteRule ^ - [L]

${ruleLines.join('\n')}
</IfModule>
ErrorDocument 404 /+not-found.html
`;

  fs.writeFileSync(OUTPUT_PATH, content, 'utf8');
  console.log(`generate-htaccess: wrote ${OUTPUT_PATH} (${routes.length} routes).`);
}

generate();
