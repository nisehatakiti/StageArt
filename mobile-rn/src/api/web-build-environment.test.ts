import { spawnSync } from 'child_process';
import * as fs from 'fs';
import * as os from 'os';
import * as path from 'path';

import { DEV_API_BASE_URL, PROD_API_BASE_URL, PUBLIC_WEB_BASE_URLS, VERIFICATION_API_BASE_URL, VERIFICATION_PUBLIC_WEB_BASE_URL } from './environment';

// eslint-disable-next-line @typescript-eslint/no-require-imports
const { verify, AUTH_PAGES } = require('../../scripts/verify-web-build');

/**
 * PR #7 R14-B stage 11 (test/r14b-verification-environment): the isolated
 * verification API (verify-api.stageart.top) is used only when named
 * explicitly, and the build checks keep every API URL out of every other
 * build - in particular the verification URL out of Dev / Production
 * builds. Uses a minimal fake app export (no real `expo export`).
 */
const SCRIPTS_DIR = path.resolve(__dirname, '../../scripts');
const tempDirs: string[] = [];

function fakeAppExport(bundle: string, html = '<div>app</div>'): string {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'stageart-webbuild-'));
  tempDirs.push(dir);
  const write = (rel: string, content: string) => {
    fs.mkdirSync(path.dirname(path.join(dir, rel)), { recursive: true });
    fs.writeFileSync(path.join(dir, rel), content);
  };
  for (const page of AUTH_PAGES as string[]) write(page, '<div></div>');
  write('login.html', '<input data-testid="login-email" />');
  write('index.html', html);
  write('[organizationSlug].html', '<div></div>');
  write('[organizationSlug]/[productionSlug].html', '<div></div>');
  write('.htaccess', 'RewriteRule x [organizationSlug]/[productionSlug].html\nRewriteRule y [organizationSlug].html\n');
  write('_expo/static/js/web/entry-test.js', bundle);
  return dir;
}

afterAll(() => {
  for (const dir of tempDirs) fs.rmSync(dir, { recursive: true, force: true });
});

describe('verify-web-build: API URLs', () => {
  it.each([
    ['development', DEV_API_BASE_URL],
    ['production', PROD_API_BASE_URL],
    ['verification', VERIFICATION_API_BASE_URL],
  ])('accepts an app build that references only the %s API', (api, url) => {
    const publicUrl = PUBLIC_WEB_BASE_URLS[api as keyof typeof PUBLIC_WEB_BASE_URLS];
    expect(verify({ dir: fakeAppExport(`const base="${url}";const pub="${publicUrl}";`), site: 'app', api })).toEqual([]);
  });

  it.each([
    ['development', VERIFICATION_API_BASE_URL],
    ['production', VERIFICATION_API_BASE_URL],
    ['production', DEV_API_BASE_URL],
    ['development', PROD_API_BASE_URL],
    ['verification', DEV_API_BASE_URL],
    ['verification', PROD_API_BASE_URL],
  ])('rejects a %s build whose bundle also references %s', (api, otherUrl) => {
    const expected = { development: DEV_API_BASE_URL, production: PROD_API_BASE_URL, verification: VERIFICATION_API_BASE_URL }[api]!;
    const errors: string[] = verify({ dir: fakeAppExport(`a="${expected}";b="${otherUrl}";`), site: 'app', api });
    expect(errors.some((e) => e.includes(otherUrl))).toBe(true);
  });

  it.each([['development'], ['production']])('rejects a %s build that contains the verification public Web URL (bundle or HTML)', (api) => {
    const apiUrl = api === 'production' ? PROD_API_BASE_URL : DEV_API_BASE_URL;
    const publicUrl = PUBLIC_WEB_BASE_URLS[api as keyof typeof PUBLIC_WEB_BASE_URLS];
    const inBundle: string[] = verify({ dir: fakeAppExport(`a="${apiUrl}";p="${publicUrl}";v="${VERIFICATION_PUBLIC_WEB_BASE_URL}/x/";`), site: 'app', api });
    expect(inBundle).toEqual([`a bundle references the verification public Web URL (${VERIFICATION_PUBLIC_WEB_BASE_URL})`]);
    const inHtml: string[] = verify({ dir: fakeAppExport(`a="${apiUrl}";p="${publicUrl}";`, `<a href="${VERIFICATION_PUBLIC_WEB_BASE_URL}/">x</a>`), site: 'app', api });
    expect(inHtml).toEqual([`index.html references the verification public Web URL (${VERIFICATION_PUBLIC_WEB_BASE_URL})`]);
  });

  it('rejects a verification build without the verification public Web URL', () => {
    const errors: string[] = verify({ dir: fakeAppExport(`a="${VERIFICATION_API_BASE_URL}";p="${PUBLIC_WEB_BASE_URLS.development}";`), site: 'app', api: 'verification' });
    expect(errors).toEqual([`no bundle references the verification public Web URL (${VERIFICATION_PUBLIC_WEB_BASE_URL})`]);
  });

  it('rejects a verification build that does not reference the verification API', () => {
    const errors: string[] = verify({ dir: fakeAppExport(`a="${DEV_API_BASE_URL}";`), site: 'app', api: 'verification' });
    expect(errors.some((e) => e.includes(`no bundle references the verification API`))).toBe(true);
  });

  it.each([undefined, '', 'staging', 'Verification', 'verify'])('rejects the unknown --api value %p', (api) => {
    expect(verify({ dir: fakeAppExport('x'), site: 'app', api })).toEqual(['--api must be "development", "production" or "verification"']);
  });

  it('accepts verification for the app site only', () => {
    expect(verify({ dir: fakeAppExport('x'), site: 'public', api: 'verification' })).toEqual([
      '--api verification is only for the app site (--site app)',
    ]);
  });
});

describe('export-web-site: argument checks (fail before any export)', () => {
  const run = (args: string[]) =>
    spawnSync(process.execPath, [path.join(SCRIPTS_DIR, 'export-web-site.js'), ...args], { encoding: 'utf8' });

  it.each([['staging'], ['Verification'], ['']])('refuses the --api value %p', (api) => {
    const result = run(['--site', 'app', '--api', api, '--out', path.join(os.tmpdir(), 'stageart-never-created')]);
    expect(result.status).toBe(1);
    expect(result.stderr).toContain('--api must be "development", "production" or "verification"');
  });

  it('refuses --api verification for the public site', () => {
    const result = run(['--site', 'public', '--api', 'verification', '--out', path.join(os.tmpdir(), 'stageart-never-created')]);
    expect(result.status).toBe(1);
    expect(result.stderr).toContain('--api verification is only for the app site (--site app)');
  });
});
