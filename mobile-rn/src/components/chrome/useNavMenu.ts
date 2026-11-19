import { usePathname, type Href } from 'expo-router';

import {
  canEditOrganizationInfo,
  canManageOrganizationAccounting,
  canManageOrganizationMembers,
  canManageOrganizationProductions,
  canTransferOrganizationOwner,
} from '@/features/organization/permissions';
import { useOrganizations } from '@/features/organization/useOrganizations';
import { useMyParticipatingProductions } from '@/features/participant/useParticipant';
import {
  canEditProductionInfo,
  canManageProductionCheckIn,
  canManageProductionDelegates,
  canManageProductionParticipants,
  canManageProductionPerformances,
  canManageProductionQuestionnaire,
  canManageProductionSettlement,
  canManageProductionTickets,
  canOpenProductionSchedule,
  canViewProductionAccounting,
} from '@/features/production/permissions';
import { useProduction } from '@/features/production/useProductions';
import type { Organization, Production } from '@/types/api';

/** A menu entry. There is no disabled state: an item the Person may not use is not listed at all. */
export type NavMenuItem = { key: string; label: string; href: Href; groupLabel?: string };

export type ContextAreaType = 'home' | 'organization' | 'production';

/**
 * StageArt Phase 1 (docs/04-CommonNavigationDesign.md, Context Area
 * design confirmed 2026-09-07, `c06cc8d`, adopted over
 * docs/12-FunctionalStructure.md §15.1's un-migrated flat-menu text
 * per explicit user decision during this Phase's pre-work
 * reconciliation - see this Phase's completion report): which Context
 * is active is derived purely from the current route, not from any
 * sticky "last selected Organization" state (OrganizationContext.tsx
 * serves a different purpose - Query scoping - and does not reset when
 * navigating away, so it cannot answer "what is the user looking at
 * right now").
 *
 * Matches `/organizations/{id}/...` (excluding the literal `/organizations/create`
 * segment) and `/production(s)/{id}/...` (both the Web card-grid family
 * `productions/[id]/...` and the native Tab-shell family
 * `production/[id]/...` resolve to the same Production Context).
 */
function useCurrentContext(): { type: ContextAreaType; organizationId: string | null; productionId: string | null } {
  const pathname = usePathname();

  const orgMatch = pathname.match(/^\/organizations\/([^/]+)(\/|$)/);
  if (orgMatch && orgMatch[1] !== 'create') {
    return { type: 'organization', organizationId: orgMatch[1], productionId: null };
  }

  const prodMatch = pathname.match(/^\/productions?\/([^/]+)(\/|$)/);
  if (prodMatch && prodMatch[1] !== 'create') {
    return { type: 'production', organizationId: null, productionId: prodMatch[1] };
  }

  return { type: 'home', organizationId: null, productionId: null };
}

/**
 * Home Context (§4 of this Phase's instruction): entry points that
 * already have a real destination screen. お知らせ/通知/稽古予定 are
 * intentionally NOT items here - they are not their own screens, they
 * already render inline on Home itself (PersonalOverviewSection, see
 * home.tsx) and adding a duplicate Context Area entry for them would
 * not "reuse existing implemented wiring", it would just be a second
 * path to content already visible on the page the user is on.
 */
const HOME_CONTEXT_ITEMS: NavMenuItem[] = [
  { key: 'discover-organizations', label: '団体を探す', href: '/discover-organizations' as Href },
  { key: 'discover-productions', label: '公演を探す', href: '/discover-productions' as Href },
  { key: 'favorites', label: 'お気に入り', href: '/favorites' as Href },
  { key: 'my-organizations', label: '所属団体', href: '/organizations' as Href },
  { key: 'participating-productions', label: '参加中の公演・活動', href: '/participating-productions' as Href },
  { key: 'viewing-history', label: '観劇履歴', href: '/viewing-history' as Href },
];

/**
 * Organization Context: the organization menu is intentionally kept small.
 * 「団体情報」 is the single entry point for the Organization's identity and
 * public-page information (ABOUT/SNS/links/logo are not separate menu items).
 * 「メンバー管理」 is the single entry point for roster operations; its
 * add/delegate actions are grouped underneath it, matching Production
 * member management. Participation approvals belong on Home, and invitations
 * are handled from Member Management rather than as a separate Organization
 * menu item. Past-production registration uses the normal Production creation
 * flow, so it has no separate menu entry.
 *
 * Each item is listed only for those the backend lets use it
 * (features/organization/permissions.ts, the same rules the screens use);
 * nothing is shown disabled (docs/10-Architecture/ManagementNavigationPolicy.md:
 * 権限のない管理機能はメニューから表示しない). A member or anyone outside the
 * Organization therefore gets no management item; the API still refuses
 * them on its own.
 * A delegate (ORGANIZATION_MANAGER) gets the items of the 管理領域 the
 * Owner gave them (current_person_management_areas), nothing more:
 *   団体情報        Owner, every delegate (tied to the Role, not an area)
 *   メンバー管理    Owner, delegate with メンバー管理 (MEMBERS)
 *   代表者交代      Owner only
 *   公演・活動一覧  Owner, delegate with 公演管理 (PRODUCTIONS)
 *   会計管理        Owner, delegate with 会計管理 (ACCOUNTING); only when accounting is enabled
 */

function buildOrganizationContextItems(id: string, organization: Organization | undefined): NavMenuItem[] {
  const items: NavMenuItem[] = [];

  if (canEditOrganizationInfo(organization)) {
    items.push({ key: 'organization-info', label: '団体情報', href: `/organizations/${id}/edit` as Href });
  }

  if (canManageOrganizationMembers(organization)) {
    items.push({ key: 'organization-members', label: 'メンバー管理', href: `/organizations/${id}/members` as Href, groupLabel: 'メンバー管理' });
  }

  if (canTransferOrganizationOwner(organization)) {
    items.push({
      key: 'organization-members-owner-transfer',
      label: '代表者交代',
      href: `/organizations/${id}/members/owner-transfer` as Href,
    });
  }

  if (canManageOrganizationProductions(organization)) {
    items.push(
      { key: 'organization-productions', label: '公演・活動一覧', href: `/organizations/${id}/productions` as Href, groupLabel: '公演・活動管理' }
    );
  }

  if (organization?.accounting_enabled && canManageOrganizationAccounting(organization)) {
    items.push({ key: 'organization-accounting', label: '会計管理', href: `/organizations/${id}/accounting` as Href });
  }

  return items;
}

/**
 * Production Context basic structure per this Phase's explicit
 * instruction §4. (History: チケット管理／小屋入り～本番／公演終了・精算処理
 * were first disabled placeholders ahead of their features; the items
 * without permission are now hidden instead - see the last paragraph.)
 *
 * Gating history (the items below were "disabled", now hidden):
 * (公演情報: disabled for non-Primary-Manager; メンバー管理: disabled
 * unless Primary Manager or a PARTICIPANT_MANAGER Delegate; 稽古管理:
 * always available; 公演スケジュール管理 - added by Phase 2 Performance基盤 -
 * disabled unless Primary Manager or a PERFORMANCE_MANAGER Delegate;
 * チケット管理 - enabled by Phase 3 Ticket/Reservation基盤, previously a
 * permanent disabled placeholder - disabled unless Primary Manager or a
 * TICKET_MANAGER Delegate, per instruction §23/§24). 小屋入り～本番／
 * 公演終了・精算処理 - enabled by Phase 4 Check-in/精算/会計連携 (previously
 * permanent disabled placeholders): 小屋入り～本番 disabled unless Primary
 * Manager or a CHECKIN_MANAGER Delegate; 公演終了・精算処理 (精算) stays
 * PrimaryManager-only, mirroring SettlementCapability::MANAGE's own
 * backend default (no Delegate Role currently grants it).
 *
 * StageArt 小屋入り～本番接続 instruction (docs/04-CommonNavigationDesign.md
 * §20.6 確定事項): "「小屋入り～本番」の配下には「タイムテーブル」「受付」を
 * 配置する" - split into two items sharing the same 小屋入り～本番
 * groupLabel (rendered once, above the first child - see
 * WebSidebarNav/NativeDrawerMenu). タイムテーブル points at the existing
 * Production Schedule screen (`/production/{id}/schedule`); 受付 keeps
 * its existing destination and key unchanged.
 *
 * StageArt UI再構成 instruction (this round §「Production Context」):
 * ユーザー向け表記を「公演回」から「公演スケジュール」へ統一（内部route/
 * ドメインコードのPerformance等は無変更）- label changed from 公演回管理
 * to 公演スケジュール管理; destination (`/productions/{id}/performances`)
 * unchanged, since this round's own指示 confirmed "公演スケジュール管理:
 * Performance管理を利用"（a label change, not a new/separate screen）。
 * 「担当者」「アンケート」は今回の確定リストに明記されていないが、削除する
 * 仕様も見当たらないため既存のまま維持する（要確認）。
 *
 * メニューの非表示統一: an item the signed-in Person may not use is left
 * out entirely, as in the Organization Context - nothing is rendered
 * disabled (docs/04-CommonNavigationDesign.md 「権限のない項目は表示しない」).
 * Each condition comes from features/production/permissions.ts, the same
 * rules the screens behind the items check. 予定管理 stays for everyone in
 * the Production (participants use it). 公演終了／精算処理 follows
 * Settlement.Manage on the server: PrimaryManager or ACCOUNTING_MANAGER.
 *
 * 会計 (ManagementNavigationPolicy.md "Production Context": 公演 > 会計 >
 * 予算 / 実績 / 経費承認 / 決算): the Production's own accounting, grouped
 * under one 会計 heading - 予算 opens the Budget list, 実績 the Production
 * Accounting Summary (予算・実績・差異). Listed for the PrimaryManager or an
 * ACCOUNTING_MANAGER only, the same rule as the 会計 tab and the API.
 * 経費承認 and 決算 have no menu entry yet.
 */
function buildProductionContextItems(id: string, production: Production | undefined, isParticipant: boolean): NavMenuItem[] {
  const schedule: NavMenuItem = { key: 'production-rehearsal', label: '予定管理', href: `/production/${id}/schedule/attendance` as Href };
  // A plain participant cannot read GET /productions/{id} (403), so the
  // Production is undefined for them: they get 予定管理 only - the
  // participant-facing entry - and never a management item. Someone outside
  // the Production gets nothing.
  if (!production) return isParticipant ? [schedule] : [];
  const items: (NavMenuItem & { visible: boolean })[] = [
    { key: 'production-info', label: '公演情報', href: `/productions/${id}/edit` as Href, visible: canEditProductionInfo(production) },
    { key: 'production-delegates', label: '担当者', href: `/productions/${id}/delegates` as Href, visible: canManageProductionDelegates(production) },
    { key: 'production-members', label: 'メンバー管理', href: `/productions/${id}/participants` as Href, visible: canManageProductionParticipants(production) },
    { ...schedule, visible: canOpenProductionSchedule(production, isParticipant) },
    {
      key: 'production-performances',
      label: '公演スケジュール管理',
      href: `/productions/${id}/performances` as Href,
      visible: canManageProductionPerformances(production),
    },
    { key: 'production-ticket', label: 'チケット管理', href: `/productions/${id}/tickets` as Href, visible: canManageProductionTickets(production) },
    { key: 'production-ticket-quota', label: 'チケットノルマ', href: `/productions/${id}/quota` as Href, visible: canManageProductionTickets(production) },
    {
      key: 'production-timetable',
      label: 'タイムテーブル',
      href: `/production/${id}/schedule` as Href,
      groupLabel: '小屋入り～本番',
      visible: canManageProductionCheckIn(production),
    },
    { key: 'production-reception', label: '受付', href: `/productions/${id}/checkin` as Href, visible: canManageProductionCheckIn(production) },
    { key: 'production-settlement', label: '公演終了／精算処理', href: `/productions/${id}/settlement` as Href, visible: canManageProductionSettlement(production) },
    { key: 'production-questionnaire', label: 'アンケート', href: `/productions/${id}/questionnaire` as Href, visible: canManageProductionQuestionnaire(production) },
    {
      key: 'production-accounting-budget',
      label: '予算',
      href: `/productions/${id}/budgets` as Href,
      groupLabel: '会計',
      visible: canViewProductionAccounting(production),
    },
    { key: 'production-accounting', label: '実績', href: `/production/${id}/accounting` as Href, visible: canViewProductionAccounting(production) },
  ];
  return items.filter((item) => item.visible).map(({ visible: _visible, ...item }) => item);
}

/**
 * Fixed area (§2 of this Phase's instruction): always the same four
 * destinations regardless of Context. There is no dedicated マイページ
 * or 設定 screen - per this Phase's explicit instruction ("プロフィールは
 * 共通固定領域の「マイページ」配下", "アカウント管理は共通固定領域の「設定」
 * 配下"), these fixed items ARE the entry point that existing プロフィール
 * (/profile) and アカウント管理 (/account) screens already serve, not a
 * new screen wrapping them.
 */
const FIXED_ITEMS: NavMenuItem[] = [
  { key: 'home', label: 'ホーム', href: '/home' as Href },
  { key: 'mypage', label: 'マイページ', href: '/profile' as Href },
  { key: 'settings', label: '設定', href: '/account' as Href },
];

/**
 * StageArt UI再構成 instruction (this round §「戻る・Context切替」):
 * Organization Contextの「戻る」は静的な固定リンク（追加のデータ取得
 * 不要）なのでこのまま提供する。
 *
 * Production -> Organization の動的な「戻る」（Production自身に
 * organization_idが無く、Project経由の解決が必要 - useProductionOrganization
 * 参照）は、このラウンドで実装を試みたが、Production Context配下の
 * 既存テスト19件で無関係な副作用（fireEvent操作時の入力値がstateへ反映
 * されなくなる、Jest/RTLのこのハーネス固有の既知の脆弱性クラス）を起こす
 * ことが判明したため、安全のため今回は見送った。グローバルなuseNavMenu()
 * へ新しい非同期処理を追加したこと自体が原因と考えられるが、正確な機序は
 * 特定できていない。次回、Production側にorganization_idを直接持たせる等
 * バックエンド変更を伴う解決策を検討する必要がある（要確認・今回の作業
 * 報告参照）。
 */
const ORGANIZATION_BACK_TO: NavMenuItem = { key: 'back-to-organizations', label: '← 所属団体一覧へ戻る', href: '/organizations' as Href };

export function useNavMenu() {
  const context = useCurrentContext();
  const organizationsQuery = useOrganizations();
  const productionQuery = useProduction(context.productionId ?? undefined);
  // Participation is asked for only when it decides something: inside a
  // Production Context, once the Production fetch has settled without a
  // Production (the server refuses it to anyone but the PrimaryManager /
  // delegates, who get 予定管理 from the Production itself). Shares the cached
  // ['my-participating-productions'] query with 参加している公演・活動.
  const needsParticipation =
    context.type === 'production' && !!context.productionId && !productionQuery.isLoading && !productionQuery.data;
  const participatingQuery = useMyParticipatingProductions({ enabled: needsParticipation });
  const isParticipant =
    needsParticipation && (participatingQuery.data ?? []).some((row) => row.production_id === context.productionId);

  if (context.type === 'organization' && context.organizationId) {
    const organization = organizationsQuery.data?.find((org) => org.id === context.organizationId);
    return {
      fixedItems: FIXED_ITEMS,
      contextType: 'organization' as const,
      contextLabel: organization?.name ?? '団体',
      contextItems: buildOrganizationContextItems(context.organizationId, organization),
      backTo: ORGANIZATION_BACK_TO,
    };
  }

  if (context.type === 'production' && context.productionId) {
    const production = productionQuery.data;
    return {
      fixedItems: FIXED_ITEMS,
      contextType: 'production' as const,
      contextLabel: production?.name ?? '公演',
      contextItems: buildProductionContextItems(context.productionId, production, isParticipant),
      // See ORGANIZATION_BACK_TO's own docblock for why this is not yet
      // implemented for Production Context.
      backTo: null,
    };
  }

  return {
    fixedItems: FIXED_ITEMS,
    contextType: 'home' as const,
    contextLabel: 'ホーム',
    contextItems: HOME_CONTEXT_ITEMS,
    backTo: null,
  };
}
