export type StageArtRootExperience = 'public-landing' | 'application';

const PUBLIC_MARKETING_HOSTS = new Set(['stageart.top', 'www.stageart.top', 'dummy.stageart.top']);
const APPLICATION_HOSTS = new Set(['app.stageart.top', 'dev.stageart.top']);

function normalizeHostname(hostname: string): string {
  return hostname.trim().toLowerCase().replace(/\.$/, '');
}

export function getStageArtRootExperience(hostname: string): StageArtRootExperience {
  const normalizedHost = normalizeHostname(hostname);
  if (PUBLIC_MARKETING_HOSTS.has(normalizedHost)) return 'public-landing';
  if (APPLICATION_HOSTS.has(normalizedHost)) return 'application';
  // Local development and unknown hosts retain the existing application
  // entry flow so local Expo work does not unexpectedly become marketing-only.
  return 'application';
}

export function getStageArtLoginUrl(hostname: string): string {
  const normalizedHost = normalizeHostname(hostname);
  if (normalizedHost === 'dummy.stageart.top') return 'https://dev.stageart.top/login/';
  if (normalizedHost === 'stageart.top' || normalizedHost === 'www.stageart.top') {
    return 'https://app.stageart.top/login/';
  }
  return 'https://app.stageart.top/login/';
}

/**
 * The public site (feature landing + organization/production public pages)
 * never asks for a login: only the application hosts own the login screen
 * and the authenticated area.
 */
export function isPublicSiteHost(hostname: string): boolean {
  return getStageArtRootExperience(hostname) === 'public-landing';
}
