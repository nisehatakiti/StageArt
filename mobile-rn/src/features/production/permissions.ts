import type { Production } from '@/types/api';

/**
 * Production-scope 担当区分 read from a Production as the API returns it.
 *
 * The API reports every ACTIVE delegate Role in `delegate_roles` (a
 * Person can hold several Roles on one Production) plus the server's own
 * decisions `is_proxy_manager`, `can_manage_production_operations`,
 * `can_manage_accounting` and `can_manage_production_delegates`. When a
 * response lacks one of those flags, the same rule is derived from
 * `is_primary_manager` and `delegate_roles`. `delegate_role` is the legacy
 * single value (only the first Role found), used only when
 * `delegate_roles` is missing.
 *
 * 担当区分 (PR #8 統合, 2026-11-24):
 * - PrimaryManager (`is_primary_manager`): everything, including 公演の完了,
 *   公演管理者の変更 and 公演資料の配布.
 * - 公演代理人 (PARTICIPANT_MANAGER and REHEARSAL_MANAGER both ACTIVE):
 *   公演情報・公開ページ・公開切替・Slug・定員・上演時間, 参加者, 参加コード,
 *   稽古, the whole accounting flow, and assigning 代理人/会計担当/受付担当.
 * - 会計担当 (ACCOUNTING_MANAGER): the accounting flow only.
 * - 稽古担当 (REHEARSAL_MANAGER without PARTICIPANT_MANAGER): 稽古 only.
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

type ProductionPermissionFields = Pick<Production, 'is_primary_manager'> &
  Partial<
    Pick<
      Production,
      | 'delegate_role'
      | 'delegate_roles'
      | 'is_proxy_manager'
      | 'can_manage_production_operations'
      | 'can_manage_accounting'
      | 'can_manage_production_delegates'
    >
  >;

type MaybeProduction = ProductionPermissionFields | null | undefined;

export function delegateRolesOf(production: MaybeProduction): string[] {
  if (!production) return [];
  if (Array.isArray(production.delegate_roles)) return production.delegate_roles;
  return production.delegate_role ? [production.delegate_role] : [];
}

export function isProxyManager(production: MaybeProduction): boolean {
  if (!production) return false;
  if (typeof production.is_proxy_manager === 'boolean') return production.is_proxy_manager;
  const roles = delegateRolesOf(production);
  return roles.includes('PARTICIPANT_MANAGER') && roles.includes('REHEARSAL_MANAGER');
}

/** PrimaryManager or 公演代理人: 公演情報 / 公開ページ / 公開切替 / Slug / 定員・上演時間 / 参加コード. */
export function canManageProductionOperations(production: MaybeProduction): boolean {
  if (!production) return false;
  if (typeof production.can_manage_production_operations === 'boolean') return production.can_manage_production_operations;
  return !!production.is_primary_manager || isProxyManager(production);
}

export function canManageJoinKeys(production: MaybeProduction): boolean {
  return canManageProductionOperations(production);
}

/** PrimaryManager, 公演代理人 or 会計担当: 予算・経費・実績・精算. */
export function canManageAccounting(production: MaybeProduction): boolean {
  if (!production) return false;
  if (typeof production.can_manage_accounting === 'boolean') return production.can_manage_accounting;
  return canManageProductionOperations(production) || delegateRolesOf(production).includes('ACCOUNTING_MANAGER');
}

/** PrimaryManager or 公演代理人: 担当（代理人・会計担当・受付担当）の任命・変更. */
export function canManageProductionDelegates(production: MaybeProduction): boolean {
  if (!production) return false;
  if (typeof production.can_manage_production_delegates === 'boolean') return production.can_manage_production_delegates;
  return !!production.is_primary_manager || isProxyManager(production);
}

/** PrimaryManager or any ACTIVE PARTICIPANT_MANAGER Role (公演代理人 included). */
export function canManageParticipants(production: MaybeProduction): boolean {
  if (!production) return false;
  return !!production.is_primary_manager || delegateRolesOf(production).includes('PARTICIPANT_MANAGER');
}

/** 公演の完了・公演管理者の変更・公演資料の配布: the PrimaryManager only. */
export function isPrimaryManagerOf(production: MaybeProduction): boolean {
  return !!production?.is_primary_manager;
}

/** Every 担当区分 the caller holds on this Production, strongest first. */
export function productionResponsibilities(production: MaybeProduction): ProductionResponsibility[] {
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
