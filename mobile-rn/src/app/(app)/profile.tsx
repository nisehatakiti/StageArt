import { ProfileContent } from '@/features/person/ProfileContent';

/**
 * StageArt Blueprint再構成 Phase 1d: `/profile` - Person information
 * only (Account/security moved to `/account`, see AccountContent.tsx).
 * Web and Native now share the exact same implementation (both already
 * used equivalent content/data hooks pre-Phase-1d; only the Platform.OS
 * branch itself is retired here, matching Home's own Phase 1c
 * consolidation).
 */
export default function ProfileScreen() {
  return <ProfileContent />;
}
