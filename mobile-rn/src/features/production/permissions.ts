import type { Production } from '@/types/api';

/**
 * Production-scope 担当区分 read from a Production as the API returns it.
 *
 * The API reports every ACTIVE delegate Role in `delegate_roles` (a
 * Person can hold several Roles on one Production) plus the server's own
 * decisions `is_proxy_manager` / `can_manage_production_operations`.
 * `delegate_role` is the legacy single value (only the first Role found)
 * and is used here only as a fallback for older responses.
 *
 * 担当区分:
 * - PrimaryManager: `is_primary_manager`
 * - 公演代理人: PARTICIPANT_MANAGER and REHEARSAL_MANAGER both ACTIVE
 * - 稽古担当: REHEARSAL_MANAGER without PARTICIPANT_MANAGER
 * - 会計担当: ACCOUNTING_MANAGER (no such Role on this code line yet; never
 *   grants Production management here)
 *
 * These only decide what the screen shows. The API authorizes every
 * operation on its own.
 */
export type ProductionResponsibility =
  | 'PRIMARY_MANAGER'
  | 'PROXY_MANAGER'
  | 'REHEARSAL_MANAGER'
  | 'PARTICIPANT_MANAGER'
  | 'ACCOUNTING_MANAGER';

type ProductionPermissionFields = Pick<Production, 'is_primary_manager' | 'delegate_role'> &
  Partial<Pick<Production, 'delegate_roles' | 'is_proxy_manager' | 'can_manage_production_operations'>>;

export function delegateRolesOf(production: ProductionPermissionFields | null | undefined): string[] {
  if (!production) return [];
  if (Array.isArray(production.delegate_roles)) return production.delegate_roles;
  return production.delegate_role ? [production.delegate_role] : [];
}

export function isProxyManager(production: ProductionPermissionFields | null | undefined): boolean {
  if (!production) return false;
  if (typeof production.is_proxy_manager === 'boolean') return production.is_proxy_manager;
  const roles = delegateRolesOf(production);
  return roles.includes('PARTICIPANT_MANAGER') && roles.includes('REHEARSAL_MANAGER');
}

/** PrimaryManager or 公演代理人: information / public page / publication / Slug / Join Keys. */
export function canManageProductionOperations(production: ProductionPermissionFields | null | undefined): boolean {
  if (!production) return false;
  if (typeof production.can_manage_production_operations === 'boolean') return production.can_manage_production_operations;
  return production.is_primary_manager || isProxyManager(production);
}

export function canManageJoinKeys(production: ProductionPermissionFields | null | undefined): boolean {
  return canManageProductionOperations(production);
}

/** PrimaryManager or any ACTIVE PARTICIPANT_MANAGER Role (公演代理人 included). */
export function canManageParticipants(production: ProductionPermissionFields | null | undefined): boolean {
  if (!production) return false;
  return production.is_primary_manager || delegateRolesOf(production).includes('PARTICIPANT_MANAGER');
}

/** Every 担当区分 the caller holds on this Production, strongest first. */
export function productionResponsibilities(
  production: ProductionPermissionFields | null | undefined
): ProductionResponsibility[] {
  if (!production) return [];
  const roles = delegateRolesOf(production);
  const result: ProductionResponsibility[] = [];
  if (production.is_primary_manager) result.push('PRIMARY_MANAGER');
  if (isProxyManager(production)) {
    result.push('PROXY_MANAGER');
  } else {
    if (roles.includes('REHEARSAL_MANAGER')) result.push('REHEARSAL_MANAGER');
    if (roles.includes('PARTICIPANT_MANAGER')) result.push('PARTICIPANT_MANAGER');
  }
  if (roles.includes('ACCOUNTING_MANAGER')) result.push('ACCOUNTING_MANAGER');
  return result;
}

export const PRODUCTION_RESPONSIBILITY_LABEL: Record<ProductionResponsibility, string> = {
  PRIMARY_MANAGER: 'PrimaryManager',
  PROXY_MANAGER: '公演代理人',
  REHEARSAL_MANAGER: '稽古担当',
  PARTICIPANT_MANAGER: '参加者担当',
  ACCOUNTING_MANAGER: '会計担当',
};
