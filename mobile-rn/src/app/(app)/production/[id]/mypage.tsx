import { ProfileContent } from '@/features/person/ProfileContent';

/**
 * StageArt Blueprint再構成 Phase 1d §9: this tab's content is entirely
 * Person-scoped (never reads the Production [id] param - unchanged from
 * before this Phase) and is one of the Production Shell's fixed tabs
 * (production/[id]/_layout.tsx's own Tabs, confirmed required by
 * production-shell.test.tsx's "shows all 3 fixed Blueprint tabs" check -
 * not something this Phase removes).
 *
 * Judgment call (flagged per §9's own instruction rather than decided
 * silently): before this Phase, this tab rendered the same combined
 * MyPageContent as top-level /profile (Person info + Account/security +
 * Logout, all in one). Now that those are two separate screens, this one
 * fixed tab slot can only point at one of them - ProfileContent was
 * chosen because AppChrome (mounted once at app/(app)/_layout.tsx, wraps
 * this Production Shell too) already makes "アカウント管理"/"ログアウト"
 * one tap away from this exact screen regardless, so nothing Account-
 * related actually becomes unreachable from here - only Person
 * information (name/所属団体/参加公演) is Production-tab-local content
 * with no other equally-close entry point. If this reads wrong for the
 * intended Production-context use of this tab, please say so - happy to
 * change it.
 */
export default function MyPageScreen() {
  return <ProfileContent />;
}
