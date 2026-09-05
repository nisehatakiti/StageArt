import type { Href } from 'expo-router';

import { useHasAccountingActivity } from '@/features/accounting/useHasAccountingActivity';
import { useOrganizations } from '@/features/organization/useOrganizations';
import { useMyManagedProductions } from '@/features/production/useMyManagedProductions';
import type { Organization } from '@/types/api';

/** See this file's own docblock (§9 correction) for why OWNER is the
 * accurate definition today rather than a placeholder for "admin" -
 * update this single function, not its call sites, once an
 * Organization-scope Delegate exists in the Domain. */
function hasOrganizationManagementPermission(organization: Organization): boolean {
  return organization.current_person_role === 'OWNER';
}

export type NavMenuItem = { key: string; label: string; href: Href };

/**
 * StageArt Blueprint再構成 Phase 1 §8: the common menu(団体を探す/公演を
 * 探す/プロフィール/経費精算/アカウント管理) plus the conditional admin menu
 * (団体情報/公演情報), computed once here so both WebSidebarNav and
 * NativeDrawerMenu render the exact same items from the exact same
 * authorization source instead of re-deriving it.
 *
 * StageArt Blueprint再構成 Phase 1c §3: "経費精算" is shown only when
 * `useHasAccountingActivity()` finds real accounting data (Budget/
 * Actual) on a Production the Person is affiliated with - see that
 * hook's own docblock for why this is Production-affiliation-only (no
 * Organization-level accounting summary endpoint exists to check
 * against literally). Not gated on management permission - §3's own
 * condition is "所属・参加している", not "管理権限を持つ".
 *
 * Admin menu items resolve directly to the one Organization/Production
 * the Person manages when there's exactly one (mirrors home.tsx's own
 * "single Membership auto-selects" precedent), otherwise to the list
 * screen - no new picker UI needed for this Phase.
 *
 * StageArt Blueprint再構成 Phase 1 §9 (2026-09-02 correction): "団体情報"
 * must show for anyone holding Organization management permission, not
 * "OWNER" as a permanent hardcoded definition - Organization
 * Administrators are meant to sit alongside an Organization-scope
 * Management Delegate. Checked the current Domain/API for one
 * (RoleKey.php, OrganizationAuthorizationService.php, Membership
 * fields exposed on `Organization`): **no Organization-scope Delegate
 * exists today** - Delegate is a Production-only concept
 * (`ProductionDelegate`/`delegate_role`, used below for "公演情報").
 * `current_person_role === 'OWNER'` is therefore not a stand-in for
 * "has admin permission" - for Organization scope it currently *is*
 * the complete, accurate definition (the only two Roles are
 * OWNER/MEMBER). This is a real Domain gap, not a Navigation-layer
 * shortcut: flagged in the Phase 1 report rather than inventing an
 * Organization Delegate concept here. Once one exists, replace this
 * check with the equivalent "has Organization management permission"
 * read (mirroring how `hasProductionManagementPermission` below reads
 * `is_primary_manager || delegate_role` instead of a single Role).
 *
 * Production's own admin check deliberately does NOT look at
 * Participant status (Phase 9's concern, not Navigation's) - a Person
 * can be an ACTIVE Production Participant without holding any
 * management permission, and this menu must not conflate the two
 * (Blueprint §5, §9).
 */
export function useNavMenu() {
  const organizationsQuery = useOrganizations();
  const managedProductionsQuery = useMyManagedProductions();
  const hasAccountingActivity = useHasAccountingActivity();

  const basicItems: NavMenuItem[] = [
    { key: 'discover-organizations', label: '団体を探す', href: '/discover-organizations' as Href },
    { key: 'discover-productions', label: '公演を探す', href: '/discover-productions' as Href },
    { key: 'profile', label: 'プロフィール', href: '/profile' as Href },
    ...(hasAccountingActivity ? [{ key: 'accounting', label: '経費精算', href: '/participating-productions' as Href }] : []),
    { key: 'account', label: 'アカウント管理', href: '/account' as Href },
  ];

  const organizationsWithManagementPermission = organizationsQuery.data?.filter(hasOrganizationManagementPermission) ?? [];
  const managedProductions = managedProductionsQuery.data ?? [];

  const adminItems: NavMenuItem[] = [];

  if (organizationsWithManagementPermission.length > 0) {
    adminItems.push({
      key: 'org-admin',
      label: '団体情報',
      href: (organizationsWithManagementPermission.length === 1
        ? `/organizations/${organizationsWithManagementPermission[0].id}`
        : '/organizations') as Href,
    });
  }

  if (managedProductions.length > 0) {
    adminItems.push({
      key: 'production-admin',
      label: '公演情報',
      href: (managedProductions.length === 1 ? `/productions/${managedProductions[0].id}` : '/participating-productions') as Href,
    });
  }

  return { basicItems, adminItems };
}
