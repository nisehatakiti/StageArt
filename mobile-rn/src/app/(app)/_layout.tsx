import { Redirect, Stack, usePathname } from 'expo-router';
import { ActivityIndicator } from 'react-native';

import { useAuth } from '@/auth/AuthContext';
import { AppChrome } from '@/components/chrome/AppChrome';
import { ThemedView } from '@/components/themed-view';
import { returnToForUnauthenticated } from '@/utils/returnTo';

/**
 * StageArt Blueprint再構成 Phase 1: every authenticated screen lives
 * under this route group (URL paths are unaffected - a route group
 * segment never appears in the actual path). AppChrome wraps the whole
 * Stack once here, so "reachable from every authenticated screen"
 * (Blueprint §7 - account management + logout) holds structurally
 * instead of depending on each individual screen opting in (the
 * previous AppShell/WebLayout per-screen import pattern this replaces).
 *
 * Unauthenticated visitors are redirected to /login before AppChrome
 * (and its data hooks, which all require a session) ever mounts -
 * mirrors index.tsx's own status-based gate.
 */
function Loading() {
  return (
    <ThemedView style={{ flex: 1, alignItems: 'center', justifyContent: 'center' }}>
      <ActivityIndicator testID="app-layout-loading" />
    </ThemedView>
  );
}

export default function AuthenticatedLayout() {
  const { status } = useAuth();
  const pathname = usePathname();

  if (status === 'loading' || status === 'refreshing') {
    return <Loading />;
  }

  if (status === 'unauthenticated') {
    // R14-B §10: from the invitations (the invitation mail's link) the
    // person comes back there after logging in. Nothing of the invitation
    // is shown before login.
    const returnTo = returnToForUnauthenticated(pathname);
    return <Redirect href={returnTo ? { pathname: '/login', params: { return_to: returnTo } } : '/login'} />;
  }

  return (
    <AppChrome>
      <Stack screenOptions={{ headerShown: false }} />
    </AppChrome>
  );
}
