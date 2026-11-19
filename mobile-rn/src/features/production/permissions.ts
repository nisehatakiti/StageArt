import type { Production } from '@/types/api';

/**
 * Production-scope permissions as the UI sees them, read from what the
 * server returned for the signed-in Person on the Production itself
 * (`is_primary_manager` and `delegate_roles`). Each rule mirrors the
 * backend check behind the same screen (ProductionAuthorizationService /
 * RolePermissions::MAP), so menus and entry points list only what the API
 * would accept. Organization roles and 管理領域 play no part here: the
 * Production Scope has no Organization fallback on the server.
 * This only decides what the UI shows; the API still checks every request.
 */
type ProductionAccess = Pick<Production, 'is_primary_manager' | 'delegate_roles'> | null | undefined;

export function isProductionPrimaryManager(production: ProductionAccess): boolean {
  return !!production?.is_primary_manager;
}

function hasProductionDelegateRole(production: ProductionAccess, role: string): boolean {
  return isProductionPrimaryManager(production) || (production?.delegate_roles ?? []).includes(role);
}

/**
 * 予定管理 (`/production/{id}/schedule/attendance`) - a participant-facing entry,
 * not a management one: the PrimaryManager, any delegate, or an ACTIVE
 * Participant of this Production. The server returns GET /productions/{id}
 * only to the PrimaryManager or an ACTIVE delegate (canReadProduction), so a
 * loaded Production already means one of those. A plain participant gets 403
 * there; `isParticipant` (GET /me/participating-productions) covers them.
 * Being a participant opens nothing else: every other item keeps its own rule.
 */
export function canOpenProductionSchedule(production: ProductionAccess, isParticipant: boolean): boolean {
  return !!production || isParticipant;
}

/** 予定の作成 (CreateRehearsalUseCase, Rehearsal.Update) - PrimaryManager or REHEARSAL_MANAGER. */
export function canManageProductionRehearsals(production: ProductionAccess): boolean {
  return hasProductionDelegateRole(production, 'REHEARSAL_MANAGER');
}

/** 公演情報 (UpdateProductionUseCase) - PrimaryManager only. */
export function canEditProductionInfo(production: ProductionAccess): boolean {
  return isProductionPrimaryManager(production);
}

/** 担当者 (Create/DeleteProductionDelegateUseCase) - PrimaryManager only. */
export function canManageProductionDelegates(production: ProductionAccess): boolean {
  return isProductionPrimaryManager(production);
}

/** メンバー管理 and 公演・活動参加申請 (canManageParticipants) - PrimaryManager or PARTICIPANT_MANAGER. */
export function canManageProductionParticipants(production: ProductionAccess): boolean {
  return hasProductionDelegateRole(production, 'PARTICIPANT_MANAGER');
}

/** 公演スケジュール管理 (Performance) - PrimaryManager or PERFORMANCE_MANAGER. */
export function canManageProductionPerformances(production: ProductionAccess): boolean {
  return hasProductionDelegateRole(production, 'PERFORMANCE_MANAGER');
}

/** チケット管理 / チケットノルマ - PrimaryManager or TICKET_MANAGER. */
export function canManageProductionTickets(production: ProductionAccess): boolean {
  return hasProductionDelegateRole(production, 'TICKET_MANAGER');
}

/** 小屋入り～本番 (タイムテーブル / 受付, CheckIn.Manage) - PrimaryManager or CHECKIN_MANAGER. */
export function canManageProductionCheckIn(production: ProductionAccess): boolean {
  return hasProductionDelegateRole(production, 'CHECKIN_MANAGER');
}

/** 公演終了／精算処理 (Settlement.Manage) - PrimaryManager or ACCOUNTING_MANAGER. */
export function canManageProductionSettlement(production: ProductionAccess): boolean {
  return hasProductionDelegateRole(production, 'ACCOUNTING_MANAGER');
}

/**
 * 会計 (会計概要 GET /productions/{id}/accounting, Budget, Expense一覧の全件,
 * Journal Entry - Accounting.Update) - PrimaryManager or ACCOUNTING_MANAGER.
 * A general member and a delegate with any other Role see no Production-wide
 * accounting figure (ExpenseApprovalWorkflow.md "Accounting Visibility").
 */
export function canViewProductionAccounting(production: ProductionAccess): boolean {
  return hasProductionDelegateRole(production, 'ACCOUNTING_MANAGER');
}

/** アンケート (Questionnaire.Manage) - PrimaryManager or QUESTIONNAIRE_MANAGER. */
export function canManageProductionQuestionnaire(production: ProductionAccess): boolean {
  return hasProductionDelegateRole(production, 'QUESTIONNAIRE_MANAGER');
}

/** 公開設定 (publish) - PrimaryManager only. */
export function canPublishProduction(production: ProductionAccess): boolean {
  return isProductionPrimaryManager(production);
}

/** メンバー実績サマリー (GetMemberPerformanceSummaryUseCase) - PrimaryManager only. */
export function canViewProductionMemberPerformanceSummary(production: ProductionAccess): boolean {
  return isProductionPrimaryManager(production);
}

/** 参加コード (List/IssueProductionJoinKeyUseCase, DisableJoinKeyUseCase) - PrimaryManager only. */
export function canManageProductionJoinKeys(production: ProductionAccess): boolean {
  return isProductionPrimaryManager(production);
}

/** 招待管理 (`/production-invite/{id}`): 参加コード and/or 公演・活動参加申請. */
export function canOpenProductionInvite(production: ProductionAccess): boolean {
  return canManageProductionJoinKeys(production) || canManageProductionParticipants(production);
}
