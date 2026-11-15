import { Redirect, Stack } from 'expo-router';
import { ActivityIndicator } from 'react-native';

import { useAuth } from '@/auth/AuthContext';
import { AppChrome } from '@/components/chrome/AppChrome';
import { ThemedView } from '@/components/themed-view';

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

  if (status === 'loading' || status === 'refreshing') {
    return <Loading />;
  }

  if (status === 'unauthenticated') {
    return <Redirect href="/login" />;
  }

  return (
    <AppChrome>
      <Stack screenOptions={{ headerShown: false }} />
    </AppChrome>
  );
}
