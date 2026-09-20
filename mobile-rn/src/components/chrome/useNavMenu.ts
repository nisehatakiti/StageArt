import { usePathname, type Href } from 'expo-router';

import { useOrganizations } from '@/features/organization/useOrganizations';
import { useProduction } from '@/features/production/useProductions';

export type NavMenuItem = { key: string; label: string; href: Href; disabled?: boolean };

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
 * StageArt Organization Context Menu仕様整合フェーズ2
 * (docs/03-PublicPageURLAndPublicationSchedule.md「Organization Context
 * Menu」): 確定仕様の全項目をメニュー上に揃える。既存画面がある項目は
 * そのまま接続し（団体情報/メンバー管理/公演一覧/公演を作る/参加申請/
 * 招待）、画面がまだない項目（ABOUT/SNS/リンク、メンバー管理の追加/
 * 代理人を設定/代表者交代、公演管理の過去公演を登録する/公演を編集する、
 * 会計管理）は OrganizationPlaceholderScreen による「画面の器」
 * （タイトル＋準備中表示）だけを新設し、業務ロジック・入力項目・API
 * 呼び出しは一切追加していない（詳細は作業報告参照）。
 *
 * 権限ゲーティングは仕様に明記されているものだけを適用する - 団体情報は
 * 既存どおりOwner限定。参加申請/招待も既存どおりOwner限定（仕様の
 * 「メンバー管理」配下にどう位置付けるか確定できないため、独立した項目の
 * まま・ゲーティングも変更していない）。会計管理は仕様が明記する唯一の
 * 条件「団体情報で会計機能がONの場合のみ表示する」のみを適用する。それ
 * 以外の新設項目（ABOUT/SNS/リンク、メンバー管理の追加/代理人を設定/
 * 代表者交代、公演管理の過去公演を登録する/公演を編集する）には、仕様に
 * 明記されていないOwner限定等の権限ルールを推測で追加していない -
 * 「画面を開けるかどうか」と「実際に操作できるかどうか」は別問題であり、
 * 中身が未実装の骨格画面である今回はまだ後者の判断が発生しない。
 */
function buildOrganizationContextItems(id: string, isOwner: boolean, accountingEnabled: boolean): NavMenuItem[] {
  const items: NavMenuItem[] = [
    { key: 'organization-info', label: '団体情報', href: `/organizations/${id}/edit` as Href, disabled: !isOwner },
    { key: 'organization-about', label: 'ABOUT', href: `/organizations/${id}/about` as Href },
    { key: 'organization-sns', label: 'SNS', href: `/organizations/${id}/sns` as Href },
    { key: 'organization-links', label: 'リンク', href: `/organizations/${id}/links` as Href },
    { key: 'organization-members', label: 'メンバー管理', href: `/organizations/${id}/members` as Href },
    { key: 'organization-members-add', label: '追加', href: `/organizations/${id}/members/add` as Href },
    { key: 'organization-members-delegate', label: '代理人を設定', href: `/organizations/${id}/members/delegate` as Href },
    {
      key: 'organization-members-owner-transfer',
      label: '代表者交代',
      href: `/organizations/${id}/members/owner-transfer` as Href,
    },
  ];

  if (isOwner) {
    items.push(
      { key: 'organization-requests', label: '参加申請', href: `/organizations/${id}/membership-requests` as Href },
      { key: 'organization-invite', label: '招待', href: `/organizations/${id}/invite` as Href }
    );
  }

  items.push(
    { key: 'organization-productions', label: '公演一覧', href: `/organizations/${id}/productions` as Href },
    { key: 'organization-productions-create', label: '公演を作る', href: `/organizations/${id}/productions/create` as Href },
    {
      key: 'organization-productions-create-past',
      label: '過去公演を登録する',
      href: `/organizations/${id}/productions/create-past` as Href,
    },
    { key: 'organization-productions-edit', label: '公演を編集する', href: `/organizations/${id}/productions/edit` as Href }
  );

  if (accountingEnabled) {
    items.push({ key: 'organization-accounting', label: '会計管理', href: `/organizations/${id}/accounting` as Href });
  }

  return items;
}

/**
 * Production Context basic structure per this Phase's explicit
 * instruction §4. チケット管理／小屋入り～本番／公演終了・精算処理 are
 * rendered as disabled placeholders: the menu shape is allowed to exist
 * ahead of the feature (instruction §4/§6 - "将来配置されることを前提に
 * するのは問題ない"), but nothing behind them is built this Phase, so
 * they must not be clickable dead ends.
 *
 * Mirrors productions/[id]/index.tsx's own menuGrid gating exactly
 * (公演情報: disabled for non-Primary-Manager; メンバー管理: disabled
 * unless Primary Manager or a PARTICIPANT_MANAGER Delegate; 稽古管理:
 * always available; 公演回管理 - added by Phase 2 Performance基盤 - disabled
 * unless Primary Manager or a PERFORMANCE_MANAGER Delegate; チケット管理 -
 * enabled by Phase 3 Ticket/Reservation基盤, previously a permanent
 * disabled placeholder - disabled unless Primary Manager or a
 * TICKET_MANAGER Delegate, per instruction §23/§24). 小屋入り～本番／
 * 公演終了・精算処理 - enabled by Phase 4 Check-in/精算/会計連携 (previously
 * permanent disabled placeholders): 小屋入り～本番 disabled unless Primary
 * Manager or a CHECKIN_MANAGER Delegate; 公演終了・精算処理 (精算) stays
 * PrimaryManager-only, mirroring SettlementCapability::MANAGE's own
 * backend default (no Delegate Role currently grants it).
 */
function buildProductionContextItems(
  id: string,
  isPrimaryManager: boolean,
  canManageParticipants: boolean,
  canManagePerformances: boolean,
  canManageTickets: boolean,
  canManageCheckIn: boolean,
  canManageQuestionnaire: boolean
): NavMenuItem[] {
  return [
    { key: 'production-info', label: '公演情報', href: `/productions/${id}/edit` as Href, disabled: !isPrimaryManager },
    { key: 'production-delegates', label: '担当者', href: `/productions/${id}/delegates` as Href, disabled: !isPrimaryManager },
    { key: 'production-members', label: 'メンバー管理', href: `/productions/${id}/participants` as Href, disabled: !canManageParticipants },
    { key: 'production-rehearsal', label: '稽古管理', href: `/production/${id}/schedule` as Href },
    { key: 'production-performances', label: '公演回管理', href: `/productions/${id}/performances` as Href, disabled: !canManagePerformances },
    { key: 'production-ticket', label: 'チケット管理', href: `/productions/${id}/tickets` as Href, disabled: !canManageTickets },
    { key: 'production-reception', label: '小屋入り～本番', href: `/productions/${id}/checkin` as Href, disabled: !canManageCheckIn },
    { key: 'production-settlement', label: '公演終了／精算処理', href: `/productions/${id}/settlement` as Href, disabled: !isPrimaryManager },
    { key: 'production-questionnaire', label: 'アンケート', href: `/productions/${id}/questionnaire` as Href, disabled: !canManageQuestionnaire },
  ];
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

export function useNavMenu() {
  const context = useCurrentContext();
  const organizationsQuery = useOrganizations();
  const productionQuery = useProduction(context.productionId ?? undefined);

  if (context.type === 'organization' && context.organizationId) {
    const organization = organizationsQuery.data?.find((org) => org.id === context.organizationId);
    return {
      fixedItems: FIXED_ITEMS,
      contextType: 'organization' as const,
      contextLabel: organization?.name ?? '団体',
      contextItems: buildOrganizationContextItems(
        context.organizationId,
        organization?.current_person_role === 'OWNER',
        !!organization?.accounting_enabled
      ),
    };
  }

  if (context.type === 'production' && context.productionId) {
    const production = productionQuery.data;
    const isPrimaryManager = !!production?.is_primary_manager;
    const canManageParticipants = isPrimaryManager || production?.delegate_role === 'PARTICIPANT_MANAGER';
    const canManagePerformances = isPrimaryManager || production?.delegate_role === 'PERFORMANCE_MANAGER';
    const canManageTickets = isPrimaryManager || production?.delegate_role === 'TICKET_MANAGER';
    const canManageCheckIn = isPrimaryManager || production?.delegate_role === 'CHECKIN_MANAGER';
    const canManageQuestionnaire = isPrimaryManager || production?.delegate_role === 'QUESTIONNAIRE_MANAGER';
    return {
      fixedItems: FIXED_ITEMS,
      contextType: 'production' as const,
      contextLabel: production?.name ?? '公演',
      contextItems: buildProductionContextItems(
        context.productionId,
        isPrimaryManager,
        canManageParticipants,
        canManagePerformances,
        canManageTickets,
        canManageCheckIn,
        canManageQuestionnaire
      ),
    };
  }

  return {
    fixedItems: FIXED_ITEMS,
    contextType: 'home' as const,
    contextLabel: 'ホーム',
    contextItems: HOME_CONTEXT_ITEMS,
  };
}
