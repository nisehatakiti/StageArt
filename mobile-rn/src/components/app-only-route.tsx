import { Redirect } from 'expo-router';
import type { ComponentType } from 'react';

import { useIsPublicSite } from '@/hooks/use-site-host';

/**
 * Wraps an authentication screen (login, register, password reset, ...)
 * so the public site never shows it. In the public-site build
 * (EXPO_PUBLIC_STAGEART_SITE=public) the wrapped screen is never rendered
 * at all - so the exported HTML contains no form, even with JavaScript
 * disabled - and a visit returns to the public top page. In the
 * application build, a public host does the same at runtime; the
 * application hosts and native render the screen unchanged.
 *
 * The `stageartAppOnlyRoute` marker lets app-only-route-classification
 * .test.ts check that every authentication route is wrapped.
 */
export function appOnlyRoute<P extends object>(Screen: ComponentType<P>) {
  function AppOnlyRoute(props: P) {
    const isPublicSite = useIsPublicSite();

    if (isPublicSite) {
      return <Redirect href="/" />;
    }

    return <Screen {...props} />;
  }

  AppOnlyRoute.displayName = `AppOnlyRoute(${Screen.displayName ?? Screen.name})`;

  return Object.assign(AppOnlyRoute, { stageartAppOnlyRoute: true as const });
}
