#!/usr/bin/env node
// Builds one StageArt Web export for one kind of site, into its own
// directory, then generates .htaccess and verifies the result.
//
// Usage:
//   node scripts/export-web-site.js --site public|app --api development|production --out <dir>
//
//   site=public api=development  -> dummy.stageart.top
//   site=public api=production   -> stageart.top / www.stageart.top
//   site=app    api=development  -> dev.stageart.top
//   site=app    api=production   -> app.stageart.top
//
// Never writes to mobile-rn/dist (the plain `npm run export:web` output is
// left alone). `expo export` empties its output directory, so --out must
// be a new/empty directory or a previous export.
const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const { verify } = require('./verify-web-build');

const PROJECT_ROOT = path.resolve(__dirname, '..');

function parseArgs(argv) {
  const options = {};
  for (let i = 0; i < argv.length; i += 2) options[argv[i].replace(/^--/, '')] = argv[i + 1];
  return options;
}

function die(message) {
  console.error(`export-web-site: ${message}`);
  process.exit(1);
}

const { site, api, out } = parseArgs(process.argv.slice(2));

if (site !== 'public' && site !== 'app') die('--site must be "public" or "app"');
if (api !== 'development' && api !== 'production') die('--api must be "development" or "production"');
if (!out) die('--out <dir> is required');

const outDir = path.resolve(out);
const distDir = path.join(PROJECT_ROOT, 'dist');
if (outDir === distDir || outDir.startsWith(distDir + path.sep)) die(`refusing to write into ${distDir}`);
if (outDir === PROJECT_ROOT || PROJECT_ROOT.startsWith(outDir + path.sep)) die(`refusing to use the project directory (or a parent) as --out: ${outDir}`);
if (fs.existsSync(outDir) && fs.readdirSync(outDir).length > 0 && !fs.existsSync(path.join(outDir, '_expo'))) {
  die(`${outDir} is not empty and is not a previous export - refusing to let expo export clear it`);
}

const env = { ...process.env, EAS_BUILD_PROFILE: api };
delete env.EAS_BUILD_PLATFORM;
if (site === 'public') env.EXPO_PUBLIC_STAGEART_SITE = 'public';
else delete env.EXPO_PUBLIC_STAGEART_SITE;

// Both steps run through this Node binary directly (no shell), so paths
// need no quoting on any platform.
function runNode(script, args) {
  console.log(`> node ${path.relative(PROJECT_ROOT, script)} ${args.join(' ')}`);
  const result = spawnSync(process.execPath, [script, ...args], { cwd: PROJECT_ROOT, env, stdio: 'inherit' });
  if (result.status !== 0) die(`${path.basename(script)} failed (exit ${result.status})`);
}

console.log(`export-web-site: site=${site} api=${api} out=${outDir}`);
runNode(require.resolve('expo/bin/cli', { paths: [PROJECT_ROOT] }), ['export', '--platform', 'web', '--clear', '--output-dir', outDir]);
runNode(path.join(__dirname, 'generate-htaccess.js'), [outDir]);

const errors = verify({ dir: outDir, site, api });
if (errors.length) {
  console.error(`verify-web-build: FAILED (${errors.length})`);
  for (const e of errors) console.error(`  - ${e}`);
  process.exit(1);
}
console.log(`verify-web-build: OK - ${site} build, ${api} API: ${outDir}`);
