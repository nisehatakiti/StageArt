#!/usr/bin/env node
// Checks an exported StageArt Web build before it is deployed, so a build
// made for the wrong site or the wrong API cannot be shipped by mistake.
//
// Usage:
//   node scripts/verify-web-build.js <exportDir> --site public|app --api development|production
//
// --site public : the public-site build (stageart.top / www / dummy).
//                 No HTML outside the allow-list below may contain a form
//                 input - in particular no authentication form - and "/"
//                 must be the public landing page.
// --site app    : the application build (dev.stageart.top / app.stageart.top).
//                 The login page must contain the login form.
// --api         : every JavaScript bundle must reference that API host and
//                 never the other one.
//
// Read-only: never modifies the export. Exit code 1 on any failure.
const fs = require('fs');
const path = require('path');

const { DEV_API_BASE_URL, PROD_API_BASE_URL } = require('../src/api/environment');

/** Public pages that legitimately contain inputs (reservation lookup), plus
 * the email confirmation pages left unchanged until the host emails link
 * to (STAGEART_EMAIL_VERIFICATION_BASE_URL) is confirmed. */
const PUBLIC_BUILD_INPUT_ALLOW_LIST = [
  'my-reservation.html',
  'reserve/[performanceId].html',
  '[organizationSlug]/[productionSlug]/questionnaire.html',
  'verify-email.html',
  'verify-notification-email.html',
];

/** Authentication pages: must exist in both builds (same route set, same
 * .htaccess) - empty in the public build, real in the application build. */
const AUTH_PAGES = ['login.html', 'register.html', 'forgot-password.html', 'reset-password.html', 'set-name.html', 'registration-pending.html'];

/** Public slug pages and their rewrite targets: must exist in both builds. */
const PUBLIC_SLUG_PAGES = ['[organizationSlug].html', '[organizationSlug]/[productionSlug].html'];

function parseArgs(argv) {
  const [dir, ...rest] = argv;
  const options = {};
  for (let i = 0; i < rest.length; i += 2) options[rest[i].replace(/^--/, '')] = rest[i + 1];
  return { dir, site: options.site, api: options.api };
}

function listFiles(dir, prefix = '') {
  return fs.readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
    const rel = prefix ? `${prefix}/${entry.name}` : entry.name;
    return entry.isDirectory() ? listFiles(path.join(dir, entry.name), rel) : [rel];
  });
}

const countInputs = (html) => (html.match(/<(input|textarea)\b/gi) ?? []).length;

function verify({ dir, site, api }) {
  const errors = [];
  const fail = (message) => errors.push(message);

  if (!dir || !fs.existsSync(dir)) fail(`export directory not found: ${dir}`);
  if (site !== 'public' && site !== 'app') fail('--site must be "public" or "app"');
  if (api !== 'development' && api !== 'production') fail('--api must be "development" or "production"');
  if (errors.length) return errors;

  const files = listFiles(dir);
  const read = (rel) => fs.readFileSync(path.join(dir, rel), 'utf8');
  const htmlFiles = files.filter((f) => f.endsWith('.html'));

  for (const page of [...AUTH_PAGES, ...PUBLIC_SLUG_PAGES, 'index.html']) {
    if (!files.includes(page)) fail(`missing ${page}`);
  }

  const htaccess = files.includes('.htaccess') ? read('.htaccess') : '';
  if (!htaccess) fail('missing .htaccess (run scripts/generate-htaccess.js on the export)');
  else if (!htaccess.includes('[organizationSlug]/[productionSlug].html') || !htaccess.includes('[organizationSlug].html')) {
    fail('.htaccess has no organization/production slug rewrite rules');
  }

  if (site === 'public') {
    for (const file of htmlFiles) {
      if (PUBLIC_BUILD_INPUT_ALLOW_LIST.includes(file)) continue;
      const inputs = countInputs(read(file));
      if (inputs > 0) fail(`public build: ${file} contains ${inputs} form input(s)`);
    }
    if (files.includes('login.html') && read('login.html').includes('login-email')) fail('public build: login.html contains the login form');
    if (files.includes('index.html') && !read('index.html').includes('stageart-landing-headline')) {
      fail('public build: index.html is not the public landing page');
    }
  } else {
    if (files.includes('login.html') && !read('login.html').includes('login-email')) fail('app build: login.html has no login form');
    if (files.includes('index.html') && read('index.html').includes('stageart-landing-headline')) {
      fail('app build: index.html is the public landing page (was EXPO_PUBLIC_STAGEART_SITE=public set?)');
    }
  }

  const bundles = files.filter((f) => f.startsWith('_expo/static/js/') && f.endsWith('.js')).map(read);
  if (bundles.length === 0) fail('no JavaScript bundles under _expo/static/js');
  const [expected, other] = api === 'production' ? [PROD_API_BASE_URL, DEV_API_BASE_URL] : [DEV_API_BASE_URL, PROD_API_BASE_URL];
  if (!bundles.some((js) => js.includes(expected))) fail(`no bundle references the ${api} API (${expected})`);
  if (bundles.some((js) => js.includes(other))) fail(`a bundle references the other API (${other})`);

  return errors;
}

if (require.main === module) {
  const args = parseArgs(process.argv.slice(2));
  const errors = verify(args);
  if (errors.length) {
    console.error(`verify-web-build: FAILED (${errors.length})`);
    for (const e of errors) console.error(`  - ${e}`);
    process.exit(1);
  }
  console.log(`verify-web-build: OK - ${args.site} build, ${args.api} API: ${args.dir}`);
}

module.exports = { verify, PUBLIC_BUILD_INPUT_ALLOW_LIST, AUTH_PAGES };
