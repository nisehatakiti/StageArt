import { useSyncExternalStore } from 'react';
import { Platform } from 'react-native';

import { isPublicSiteHost } from '@/api/site-routing';
import { IS_PUBLIC_SITE_BUILD } from '@/config/site-build';

const subscribeToHostname = () => () => {};
const getClientHostname = () => (Platform.OS === 'web' && typeof window !== 'undefined' ? window.location.hostname : '');
const getStaticRenderHostname = () => '';

/**
 * The static web export pre-renders every page without a request host, so
 * its HTML is always the application variant. Reading window.location
 * directly during hydration made public hosts render different markup and
 * React rejected the mismatch (minified error #418). The server snapshot
 * keeps the hydration pass identical to that HTML; React then re-renders
 * with the real hostname immediately after hydrating. Client-only renders
 * (native, or a client-side navigation) read the real hostname from the
 * first render.
 */
export function useSiteHostname(): string {
  return useSyncExternalStore(subscribeToHostname, getClientHostname, getStaticRenderHostname);
}

/** True in the public-site build (always - including its exported HTML,
 * see site-build.ts), and otherwise only on Web when served from a
 * public-site host (stageart.top, www.stageart.top, dummy.stageart.top).
 * Native and the application hosts are never the public site. */
export function useIsPublicSite(): boolean {
  const hostname = useSiteHostname();
  if (IS_PUBLIC_SITE_BUILD) return true;
  return Platform.OS === 'web' && isPublicSiteHost(hostname);
}
