import { Redirect } from 'expo-router';

/**
 * StageArt Blueprint再構成 Phase 1c: `/dashboard` is no longer its own
 * screen - `home.tsx` is StageArt's single canonical Home for every
 * authenticated user (§1 - no separate admin/general-user Home). This
 * stub exists purely for URL backward-compatibility (any pre-existing
 * bookmark or link to /dashboard still resolves), not as a place to add
 * Dashboard-specific UI.
 */
export default function DashboardRedirect() {
  return <Redirect href="/home" />;
}
