import { Redirect } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator } from 'react-native';

import { useAuth } from '@/auth/AuthContext';
import { StartupAnimation } from '@/components/startup-animation';
import { ThemedView } from '@/components/themed-view';
import { useCurrentPerson } from '@/features/person/useCurrentPerson';

/** Jest sets NODE_ENV=test automatically (jest-expo preset) - the
 * startup animation is a fixed ~1.8s time-based UI concern orthogonal
 * to what every other test in this app actually verifies, and would
 * otherwise force every existing renderRouter()-mounted test that
 * passes through this gate to wait out the animation on top of its own
 * assertions. Skipped only in the test environment; plays normally in
 * the real app (dev and production builds are never NODE_ENV=test). */
const SKIP_STARTUP_ANIMATION = process.env.NODE_ENV === 'test';

function Loading() {
  return (
    <ThemedView style={{ flex: 1, alignItems: 'center', justifyContent: 'center' }}>
      <ActivityIndicator testID="auth-gate-loading" />
    </ThemedView>
  );
}

/**
 * Pure auth-gate: no UI of its own beyond the startup animation and a
 * loading spinner. Mirrors the Flutter reference implementation's
 * AuthState-driven entry point (see Phase 5.0's Flutter Feature
 * Inventory - Authentication).
 *
 * StageArt Authentication Phase 5: `refreshing` (a stored Refresh Token
 * is being exchanged for a fresh Access Token at boot - see
 * AuthContext's own docblock) is shown identically to `loading` here -
 * both are "we don't know the final answer yet", and this screen never
 * needs to distinguish them from each other.
 *
 * BusinessFlowUXClarifications.md §12's startup animation plays first,
 * in front of every other decision this screen makes - the session
 * resolution work itself (AuthProvider's boot effect) still starts
 * immediately and runs underneath it, so the animation never adds extra
 * wait time beyond whatever it would have taken anyway.
 *
 * A real-device correction removed this screen's earlier GET /me +
 * email_verified check: `status === 'authenticated'` means exactly what
 * it always used to mean - a real, persisted, verified-if-Email+Password
 * session (see AuthContext.tsx's registerWithEmail/loginWithEmail
 * docblocks for why an unverified account never reaches 'authenticated'
 * in the first place). There is nothing left for THAT check specifically
 * to do here.
 *
 * StageArt Authentication Phase 6: a GET /me check is back, but for a
 * different purpose - family_name/given_name completeness, not
 * email_verified. A restored session says nothing about whether the
 * user ever finished set-name.tsx (that can happen on any later app
 * launch, on any device, independent of when the session itself was
 * established) - so this still has to be checked fresh every time,
 * exactly like the old email_verified check used to, just for a
 * different field. Google and Email sessions are treated identically -
 * no "Google already proved identity" shortcut (this Phase's explicit
 * requirement, mirrored in login.tsx's own post-login routing).
 */
export default function Index() {
  const { status } = useAuth();
  const currentPersonQuery = useCurrentPerson();
  const [animationDone, setAnimationDone] = useState(SKIP_STARTUP_ANIMATION);

  if (!animationDone) {
    return <StartupAnimation onFinish={() => setAnimationDone(true)} />;
  }

  if (status === 'loading' || status === 'refreshing') {
    return <Loading />;
  }

  if (status === 'unauthenticated') {
    return <Redirect href="/login" />;
  }

  if (currentPersonQuery.isLoading) {
    return <Loading />;
  }

  if (currentPersonQuery.data && (!currentPersonQuery.data.family_name || !currentPersonQuery.data.given_name)) {
    return <Redirect href="/set-name" />;
  }

  return <Redirect href="/home" />;
}
