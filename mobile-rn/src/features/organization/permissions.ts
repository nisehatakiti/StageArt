import type { DelegateArea, Organization } from '@/types/api';

/**
 * Organization-scope permissions as the UI sees them, read from what the
 * server returned for the signed-in Person (`current_person_role` and
 * `current_person_management_areas` on the Organization). Each rule
 * mirrors the backend check behind the same operations, so the menu and
 * the screens show only what the API would accept. This only decides what
 * the UI shows; the API still checks every request itself.
 *
 * 代理人の管理領域 (docs/03-PublicPageURLAndPublicationSchedule.md
 * 「代理人設定」): the Owner holds every area; a delegate
 * (ORGANIZATION_MANAGER) holds only the areas the Owner gave them; a plain
 * member or an outsider holds none (OrganizationAuthorizationService::
 * hasDelegateArea on the server).
 */
type OrganizationAccess = Pick<Organization, 'current_person_role' | 'current_person_management_areas'> | null | undefined;

export function isOrganizationOwner(organization: OrganizationAccess): boolean {
  return organization?.current_person_role === 'OWNER';
}

function isOrganizationDelegate(organization: OrganizationAccess): boolean {
  return organization?.current_person_role === 'ORGANIZATION_MANAGER';
}

export function hasOrganizationManagementArea(organization: OrganizationAccess, area: DelegateArea): boolean {
  if (isOrganizationOwner(organization)) return true;
  if (!isOrganizationDelegate(organization)) return false;
  return (organization?.current_person_management_areas ?? []).includes(area);
}

/** 団体情報: UpdateOrganizationUseCase / logo / SNS / links / public page - the Owner and every delegate (tied to the Role, not an area). */
export function canEditOrganizationInfo(organization: OrganizationAccess): boolean {
  return isOrganizationOwner(organization) || isOrganizationDelegate(organization);
}

/** メンバー管理: member list / 備考 / invitations - area MEMBERS. */
export function canManageOrganizationMembers(organization: OrganizationAccess): boolean {
  return hasOrganizationManagementArea(organization, 'MEMBERS');
}

/** 公演管理: CreateProductionUseCase - area PRODUCTIONS. */
export function canManageOrganizationProductions(organization: OrganizationAccess): boolean {
  return hasOrganizationManagementArea(organization, 'PRODUCTIONS');
}

/** 会計管理 (Organization level): accounts / Organization journal entries - area ACCOUNTING. */
export function canManageOrganizationAccounting(organization: OrganizationAccess): boolean {
  return hasOrganizationManagementArea(organization, 'ACCOUNTING');
}

/** 代表者交代: OwnerTransferUseCase - Owner only. */
export function canTransferOrganizationOwner(organization: OrganizationAccess): boolean {
  return isOrganizationOwner(organization);
}

/** 代理人を設定 and each delegate's 管理領域 - Owner only. */
export function canManageOrganizationDelegates(organization: OrganizationAccess): boolean {
  return isOrganizationOwner(organization);
}

/** 参加コード (Join Key) list / issue / disable - area MEMBERS (List/IssueOrganizationJoinKeyUseCase, DisableJoinKeyUseCase). */
export function canManageOrganizationJoinKeys(organization: OrganizationAccess): boolean {
  return hasOrganizationManagementArea(organization, 'MEMBERS');
}

/** 参加申請 list / approve / reject - area MEMBERS (ListPending/Approve/RejectMembershipRequestUseCase). */
export function canReviewOrganizationMembershipRequests(organization: OrganizationAccess): boolean {
  return hasOrganizationManagementArea(organization, 'MEMBERS');
}
