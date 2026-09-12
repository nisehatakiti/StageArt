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
 * Mirrors organizations/[id]/index.tsx's own menuGrid gating exactly
 * (団体情報: disabled for non-Owner; 参加申請/招待: Owner-only, omitted
 * rather than disabled for everyone else; メンバー/公演一覧: always
 * available) so the Context Area never offers a link the target screen
 * itself would refuse.
 */
function buildOrganizationContextItems(id: string, isOwner: boolean): NavMenuItem[] {
  const items: NavMenuItem[] = [
    { key: 'organization-info', label: '団体情報', href: `/organizations/${id}/edit` as Href, disabled: !isOwner },
    { key: 'organization-members', label: 'メンバー', href: `/organizations/${id}/members` as Href },
  ];

  if (isOwner) {
    items.push(
      { key: 'organization-requests', label: '参加申請', href: `/organizations/${id}/membership-requests` as Href },
      { key: 'organization-invite', label: '招待', href: `/organizations/${id}/invite` as Href }
    );
  }

  items.push({ key: 'organization-productions', label: '公演一覧', href: `/organizations/${id}/productions` as Href });

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
 * unless Primary Manager or a PERFORMANCE_MANAGER Delegate). チケット管理
 * ／小屋入り～本番／公演終了・精算処理 are always disabled this Phase - see
 * this file's own docblock.
 */
function buildProductionContextItems(
  id: string,
  isPrimaryManager: boolean,
  canManageParticipants: boolean,
  canManagePerformances: boolean
): NavMenuItem[] {
  return [
    { key: 'production-info', label: '公演情報', href: `/productions/${id}/edit` as Href, disabled: !isPrimaryManager },
    { key: 'production-members', label: 'メンバー管理', href: `/productions/${id}/participants` as Href, disabled: !canManageParticipants },
    { key: 'production-rehearsal', label: '稽古管理', href: `/production/${id}/schedule` as Href },
    { key: 'production-performances', label: '公演回管理', href: `/productions/${id}/performances` as Href, disabled: !canManagePerformances },
    { key: 'production-ticket', label: 'チケット管理', href: `/productions/${id}` as Href, disabled: true },
    { key: 'production-reception', label: '小屋入り～本番', href: `/productions/${id}` as Href, disabled: true },
    { key: 'production-settlement', label: '公演終了／精算処理', href: `/productions/${id}` as Href, disabled: true },
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
      contextItems: buildOrganizationContextItems(context.organizationId, organization?.current_person_role === 'OWNER'),
    };
  }

  if (context.type === 'production' && context.productionId) {
    const production = productionQuery.data;
    const isPrimaryManager = !!production?.is_primary_manager;
    const canManageParticipants = isPrimaryManager || production?.delegate_role === 'PARTICIPANT_MANAGER';
    const canManagePerformances = isPrimaryManager || production?.delegate_role === 'PERFORMANCE_MANAGER';
    return {
      fixedItems: FIXED_ITEMS,
      contextType: 'production' as const,
      contextLabel: production?.name ?? '公演',
      contextItems: buildProductionContextItems(context.productionId, isPrimaryManager, canManageParticipants, canManagePerformances),
    };
  }

  return {
    fixedItems: FIXED_ITEMS,
    contextType: 'home' as const,
    contextLabel: 'ホーム',
    contextItems: HOME_CONTEXT_ITEMS,
  };
}
