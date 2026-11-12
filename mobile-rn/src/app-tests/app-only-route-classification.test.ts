import fs from 'fs';
import path from 'path';

/**
 * Public-site build: every route file must be classified, and every
 * authentication route must be wrapped with appOnlyRoute() so the public
 * site never renders (or exports HTML for) its form. Adding a new route
 * file fails this test until it is placed in one of the lists below -
 * that is the point: a new authentication screen cannot silently appear
 * on the public site.
 */
const APP_DIR = path.join(__dirname, '..', 'app');

/** Authentication screens - never shown on the public site. */
const APP_ONLY_ROUTES = ['login', 'register', 'forgot-password', 'reset-password', 'set-name', 'registration-pending'];

/** The public site's own pages (no login required). */
const PUBLIC_ROUTES = [
  'index',
  '[organizationSlug]/index',
  '[organizationSlug]/[productionSlug]/index',
  '[organizationSlug]/[productionSlug]/questionnaire',
  'reserve/[performanceId]',
  'my-reservation',
  'o/[organizationSlug]/index',
  'o/[organizationSlug]/[productionSlug]',
];

/** Email confirmation pages: left unchanged until the host that
 * STAGEART_EMAIL_VERIFICATION_BASE_URL points emails at is confirmed. */
const EMAIL_LINK_ROUTES_PENDING_DECISION = ['verify-email', 'verify-notification-email'];

function routeFiles(dir: string, prefix = ''): string[] {
  return fs.readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
    const rel = prefix ? `${prefix}/${entry.name}` : entry.name;
    if (entry.isDirectory()) return routeFiles(path.join(dir, entry.name), rel);
    return /\.tsx?$/.test(entry.name) ? [rel.replace(/\.tsx?$/, '')] : [];
  });
}

describe('public-site build: route classification', () => {
  const routes = routeFiles(APP_DIR);

  it('classifies every route file', () => {
    const unclassified = routes.filter(
      (route) =>
        !route.endsWith('_layout') &&
        !route.startsWith('(app)/') && // gated by (app)/_layout's authentication redirect
        !APP_ONLY_ROUTES.includes(route) &&
        !PUBLIC_ROUTES.includes(route) &&
        !EMAIL_LINK_ROUTES_PENDING_DECISION.includes(route)
    );

    expect(unclassified).toEqual([]);
  });

  it('lists only routes that exist', () => {
    for (const route of [...APP_ONLY_ROUTES, ...PUBLIC_ROUTES, ...EMAIL_LINK_ROUTES_PENDING_DECISION]) {
      expect(routes).toContain(route);
    }
  });

  it.each(APP_ONLY_ROUTES)('wraps the authentication route %s with appOnlyRoute()', (route) => {
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    const routeModule = require(path.join(APP_DIR, route));
    expect(routeModule.default?.stageartAppOnlyRoute).toBe(true);
  });
});
