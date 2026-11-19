import { QueryClient, QueryClientProvider, useQueryClient } from '@tanstack/react-query';
import { act, renderHook, waitFor } from '@testing-library/react-native';
import type { PropsWithChildren } from 'react';

import { createQueryClient } from '@/api/queryClient';
import { AuthProvider } from '@/auth/AuthContext';
import { useNavMenu } from '@/components/chrome/useNavMenu';
import { useOrganizations } from '@/features/organization/useOrganizations';

import { mockFetchRoutes, orgOne, orgTwo, productionOne, productionTwo, projectOne } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

let mockPathname = '/home';
jest.mock('expo-router', () => ({
  ...jest.requireActual('expo-router'),
  usePathname: () => mockPathname,
}));

function wrapper({ children }: PropsWithChildren) {
  const queryClient = new QueryClient();
  return (
    <QueryClientProvider client={queryClient}>
      <AuthProvider>{children}</AuthProvider>
    </QueryClientProvider>
  );
}

/**
 * The app's own QueryClient (api/queryClient.ts: no retry on 4xx), for the tests
 * where GET /productions/{id} is refused - the plain QueryClient above would
 * retry that 403 for seconds before the menu can settle, unlike the app.
 */
function appWrapper({ children }: PropsWithChildren) {
  return (
    <QueryClientProvider client={createQueryClient()}>
      <AuthProvider>{children}</AuthProvider>
    </QueryClientProvider>
  );
}

/** One row of GET /me/participating-productions (an ACTIVE PERSON Participant). */
function participatingRow(productionId: string) {
  return { participant_id: `participant-${productionId}`, production_id: productionId, production_name: '公演', production_slug: null, participant_type: 'CAST' };
}

const refused = { code: 'stageart_production_access_denied', message: 'denied', data: { status: 403 } };


/**
 * StageArt Phase 1: useNavMenu() now derives its Context (home /
 * organization / production) from the current route rather than from
 * admin-permission scanning across every Organization/Production the
 * Person belongs to - these tests verify that derivation and the
 * per-Context item gating directly, independent of either shell's own
 * rendering.
 */
describe('useNavMenu', () => {
  beforeEach(() => {
    mockPathname = '/home';
  });

  it('returns the four Fixed Area items and the Home Context items on /home', async () => {
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [] },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [] },
    ]);

    const { result } = await renderHook(() => useNavMenu(), { wrapper });

    expect(result.current.fixedItems.map((item) => item.key)).toEqual(['home', 'mypage', 'settings']);
    expect(result.current.contextType).toBe('home');
    expect(result.current.contextItems.map((item) => item.key)).toEqual([
      'discover-organizations',
      'discover-productions',
      'favorites',
      'my-organizations',
      'participating-productions',
      'viewing-history',
    ]);
  });

  it('switches to Organization Context on an /organizations/{id} route, enabling Owner-only items only for the Owner', async () => {
    mockPathname = `/organizations/${orgOne.id}/edit`;
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [orgOne, orgTwo] },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [] },
    ]);

    const { result } = await renderHook(() => useNavMenu(), { wrapper });

    await waitFor(() => expect(result.current.contextLabel).toBe(orgOne.name));
    expect(result.current.contextType).toBe('organization');
    const info = result.current.contextItems.find((item) => item.key === 'organization-info');
    expect(info).toBeDefined();
    expect(info && 'disabled' in info).toBe(false);
    expect(info?.href).toBe(`/organizations/${orgOne.id}/edit`);
    expect(result.current.contextItems.some((item) => item.key === 'organization-invite')).toBe(false);
  });

  /**
   * 代理人の管理領域: a delegate (ORGANIZATION_MANAGER) always gets 団体情報
   * (tied to the Role), and otherwise only the items of the areas the Owner
   * gave them (current_person_management_areas). 代表者交代 is never theirs.
   */
  async function delegateItems(areas: string[], accountingEnabled = true) {
    const delegateOrg = { ...orgTwo, current_person_role: 'ORGANIZATION_MANAGER', current_person_management_areas: areas, accounting_enabled: accountingEnabled };
    mockPathname = `/organizations/${delegateOrg.id}/edit`;
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [delegateOrg] },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [] },
    ]);

    const { result } = await renderHook(() => useNavMenu(), { wrapper });

    await waitFor(() => expect(result.current.contextLabel).toBe(delegateOrg.name));
    expect(result.current.contextItems.every((item) => !('disabled' in item))).toBe(true);
    expect(result.current.contextItems.every((item) => item.href.toString().startsWith(`/organizations/${delegateOrg.id}/`))).toBe(true);
    return result.current.contextItems.map((item) => item.key);
  }

  it('lists only 団体情報 for a delegate given no area yet', async () => {
    expect(await delegateItems([])).toEqual(['organization-info']);
  });

  it('adds メンバー管理 for a delegate given the メンバー管理 area', async () => {
    expect(await delegateItems(['MEMBERS'])).toEqual(['organization-info', 'organization-members']);
  });

  it('adds 公演・活動一覧 for a delegate given the 公演管理 area', async () => {
    expect(await delegateItems(['PRODUCTIONS'])).toEqual(['organization-info', 'organization-productions']);
  });

  it('adds 会計管理 for a delegate given the 会計管理 area, only when accounting is enabled', async () => {
    expect(await delegateItems(['ACCOUNTING'])).toEqual(['organization-info', 'organization-accounting']);
    expect(await delegateItems(['ACCOUNTING'], false)).toEqual(['organization-info']);
  });

  it('lists each area of a delegate holding several, and never 代表者交代', async () => {
    expect(await delegateItems(['PRODUCTIONS', 'ACCOUNTING'])).toEqual(['organization-info', 'organization-productions', 'organization-accounting']);
    expect(await delegateItems(['MEMBERS', 'PRODUCTIONS', 'ACCOUNTING'])).toEqual([
      'organization-info',
      'organization-members',
      'organization-productions',
      'organization-accounting',
    ]);
  });

  it('drops an item once its area is taken away (after the Organization list is reloaded)', async () => {
    const organizationsRoute = {
      test: (url: string) => url.endsWith('/organizations'),
      status: 200,
      body: [{ ...orgTwo, current_person_role: 'ORGANIZATION_MANAGER', current_person_management_areas: ['MEMBERS', 'PRODUCTIONS'] }] as unknown,
    };
    mockPathname = `/organizations/${orgTwo.id}/members`;
    mockFetchRoutes([
      organizationsRoute,
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [] },
    ]);

    const { result } = await renderHook(() => ({ nav: useNavMenu(), queryClient: useQueryClient() }), { wrapper });
    await waitFor(() => expect(result.current.nav.contextLabel).toBe(orgTwo.name));
    expect(result.current.nav.contextItems.map((item) => item.key)).toEqual(['organization-info', 'organization-members', 'organization-productions']);

    organizationsRoute.body = [{ ...orgTwo, current_person_role: 'ORGANIZATION_MANAGER', current_person_management_areas: ['MEMBERS'] }];
    await act(() => result.current.queryClient.invalidateQueries({ queryKey: ['organizations'] }));

    await waitFor(() => expect(result.current.nav.contextItems.map((item) => item.key)).toEqual(['organization-info', 'organization-members']));
  });

  it('lists every Owner item, 代表者交代 and 会計管理 included, for the Owner', async () => {
    mockPathname = `/organizations/${orgOne.id}/members`;
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [{ ...orgOne, accounting_enabled: true }] },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [] },
    ]);

    const { result } = await renderHook(() => useNavMenu(), { wrapper });

    await waitFor(() => expect(result.current.contextLabel).toBe(orgOne.name));
    expect(result.current.contextItems.map((item) => item.key)).toEqual([
      'organization-info',
      'organization-members',
      'organization-members-owner-transfer',
      'organization-productions',
      'organization-accounting',
    ]);
    expect(result.current.contextItems.every((item) => !('disabled' in item))).toBe(true);
  });

  it('lists no management item of the Organization for someone outside it', async () => {
    mockPathname = `/organizations/org-unrelated/edit`;
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [orgOne, orgTwo] },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [] },
    ]);

    const { result } = await renderHook(() => ({ nav: useNavMenu(), organizations: useOrganizations() }), { wrapper });

    // The Person's own Organizations have loaded (orgOne, orgTwo); the unrelated one is not among them.
    await waitFor(() => expect(result.current.organizations.data).toHaveLength(2));
    expect(result.current.nav.contextType).toBe('organization');
    expect(result.current.nav.contextLabel).toBe('団体');
    expect(result.current.nav.contextItems).toEqual([]);
    expect(result.current.nav.backTo?.key).toBe('back-to-organizations');
  });

  /**
   * Moving from an Organization the Person owns to one where they are a
   * plain member must not carry the first one's items over.
   */
  it('recomputes the items when switching Organizations (Owner of one, member of the other)', async () => {
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [{ ...orgOne, accounting_enabled: true }, { ...orgTwo, accounting_enabled: true }] },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [] },
    ]);

    mockPathname = `/organizations/${orgOne.id}/members`;
    const { result, rerender } = await renderHook(() => useNavMenu(), { wrapper });
    await waitFor(() => expect(result.current.contextLabel).toBe(orgOne.name));
    expect(result.current.contextItems).toHaveLength(5);
    expect(result.current.contextItems.every((item) => item.href.toString().startsWith(`/organizations/${orgOne.id}/`))).toBe(true);

    mockPathname = `/organizations/${orgTwo.id}`;
    rerender(undefined);
    await waitFor(() => expect(result.current.contextLabel).toBe(orgTwo.name));
    expect(result.current.contextItems).toEqual([]);

    mockPathname = `/organizations/${orgOne.id}`;
    rerender(undefined);
    await waitFor(() => expect(result.current.contextLabel).toBe(orgOne.name));
    expect(result.current.contextItems.map((item) => item.key)).toContain('organization-members-owner-transfer');
  });

  /**
   * StageArt Organization Context Menu仕様整合フェーズ2: the confirmed
   * spec's full item set (docs/03-PublicPageURLAndPublicationSchedule.md
   * 「Organization Context Menu」) must all be present, including the
   * newly-added skeleton-screen items - 会計管理 excluded here since
   * orgOne's fixture has accounting_enabled: false (see the dedicated
   * 会計管理 test below).
   */
  it('includes every confirmed Organization Context Menu item (skeleton screens included)', async () => {
    mockPathname = `/organizations/${orgOne.id}/edit`;
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [orgOne] },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [] },
    ]);

    const { result } = await renderHook(() => useNavMenu(), { wrapper });

    // Wait for contextLabel (only set once the Organization itself has
    // loaded), not just contextType (which flips from the URL alone,
    // before organizationsQuery.data resolves) - otherwise isOwner can
    // still read as false from a stale/loading snapshot.
    await waitFor(() => expect(result.current.contextLabel).toBe(orgOne.name));
    expect(result.current.contextItems.map((item) => item.key)).toEqual([
      'organization-info',
      'organization-members',
      'organization-members-owner-transfer',
      'organization-productions',
    ]);
  });

  it('shows 会計管理 only when the Organization has accounting_enabled', async () => {
    mockPathname = `/organizations/${orgOne.id}/edit`;
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [{ ...orgOne, accounting_enabled: true }] },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [] },
    ]);

    const { result } = await renderHook(() => useNavMenu(), { wrapper });

    await waitFor(() => expect(result.current.contextItems.some((item) => item.key === 'organization-accounting')).toBe(true));
  });

  it('lists no Organization management item at all (団体情報 included) for a MEMBER', async () => {
    mockPathname = `/organizations/${orgTwo.id}/members`;
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [{ ...orgTwo, accounting_enabled: true }] },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [] },
    ]);

    const { result } = await renderHook(() => useNavMenu(), { wrapper });

    await waitFor(() => expect(result.current.contextLabel).toBe(orgTwo.name));
    expect(result.current.contextItems.some((item) => item.key === 'organization-info')).toBe(false);
    expect(result.current.contextItems.some((item) => item.key === 'organization-invite')).toBe(false);
    // No disabled leftovers either: nothing of the Organization's management is listed.
    expect(result.current.contextItems).toEqual([]);
    // The member's own things (Home, マイページ, 設定) stay in the Fixed Area.
    expect(result.current.fixedItems.map((item) => item.key)).toEqual(['home', 'mypage', 'settings']);
    expect(result.current.backTo?.key).toBe('back-to-organizations');
  });

  it('switches to Production Context on a /productions/{id} route, hiding management items for a non-manager', async () => {
    mockPathname = `/productions/${productionTwo.id}`;
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [] },
      { test: (url) => url.endsWith(`/productions/${productionTwo.id}`), status: 200, body: productionTwo },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [] },
    ]);

    const { result } = await renderHook(() => useNavMenu(), { wrapper });

    await waitFor(() => expect(result.current.contextLabel).toBe(productionTwo.name));
    // Items without permission are not listed at all (no disabled leftovers).
    // productionTwo's delegate_role is REHEARSAL_MANAGER: only 予定管理 remains.
    expect(result.current.contextItems.map((item) => item.key)).toEqual(['production-rehearsal']);
    const rehearsal = result.current.contextItems.find((item) => item.key === 'production-rehearsal');
    expect(rehearsal?.label).toBe('予定管理');
    expect(result.current.contextItems.some((item) => 'disabled' in item)).toBe(false);
  });

  it('enables 公演スケジュール管理 for a Primary Manager, labeled 公演スケジュール管理 (not 公演回管理)', async () => {
    mockPathname = `/productions/${productionOne.id}`;
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [] },
      { test: (url) => url.endsWith(`/productions/${productionOne.id}`), status: 200, body: productionOne },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [] },
    ]);

    const { result } = await renderHook(() => useNavMenu(), { wrapper });

    await waitFor(() => expect(result.current.contextLabel).toBe(productionOne.name));
    const performances = result.current.contextItems.find((item) => item.key === 'production-performances');
    expect(performances).toBeDefined();
    expect(performances?.label).toBe('公演スケジュール管理');
  });

  it('enables 小屋入り～本番／公演終了・精算処理 for a Primary Manager (Phase 4 Check-in/精算/会計連携)', async () => {
    mockPathname = `/production/${productionOne.id}/schedule`;
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [] },
      { test: (url) => url.endsWith(`/productions/${productionOne.id}`), status: 200, body: productionOne },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [] },
    ]);

    const { result } = await renderHook(() => useNavMenu(), { wrapper });

    await waitFor(() => expect(result.current.contextLabel).toBe(productionOne.name));
    expect(result.current.contextItems.some((item) => item.key === 'production-timetable')).toBe(true);
    expect(result.current.contextItems.some((item) => item.key === 'production-reception')).toBe(true);
    expect(result.current.contextItems.some((item) => item.key === 'production-settlement')).toBe(true);
  });

  /**
   * StageArt 小屋入り～本番接続 instruction
   * (docs/04-CommonNavigationDesign.md §20.6): 小屋入り～本番 must expose
   * exactly two children - タイムテーブル and 受付 - sharing one group
   * heading, not a single flat item.
   */
  it('groups タイムテーブル and 受付 under one 小屋入り～本番 heading', async () => {
    mockPathname = `/productions/${productionOne.id}`;
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [] },
      { test: (url) => url.endsWith(`/productions/${productionOne.id}`), status: 200, body: productionOne },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [] },
    ]);

    const { result } = await renderHook(() => useNavMenu(), { wrapper });

    await waitFor(() => expect(result.current.contextLabel).toBe(productionOne.name));
    const timetable = result.current.contextItems.find((item) => item.key === 'production-timetable');
    const reception = result.current.contextItems.find((item) => item.key === 'production-reception');
    expect(timetable?.groupLabel).toBe('小屋入り～本番');
    expect(timetable?.label).toBe('タイムテーブル');
    expect(reception?.label).toBe('受付');
    expect(reception?.groupLabel).toBeUndefined();
  });

  it('lists チケット管理 for a Primary Manager', async () => {
    mockPathname = `/productions/${productionOne.id}`;
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [] },
      { test: (url) => url.endsWith(`/productions/${productionOne.id}`), status: 200, body: productionOne },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [] },
    ]);

    const { result } = await renderHook(() => useNavMenu(), { wrapper });

    await waitFor(() => expect(result.current.contextLabel).toBe(productionOne.name));
    expect(result.current.contextItems.some((item) => item.key === 'production-ticket')).toBe(true);
    expect(result.current.contextItems.some((item) => item.key === 'production-ticket-quota')).toBe(true);
  });

  it('hides チケット管理 from a REHEARSAL_MANAGER delegate (not TICKET_MANAGER)', async () => {
    mockPathname = `/productions/${productionTwo.id}`;
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [] },
      { test: (url) => url.endsWith(`/productions/${productionTwo.id}`), status: 200, body: productionTwo },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [] },
    ]);

    const { result } = await renderHook(() => useNavMenu(), { wrapper });

    // Wait for the Production itself, so "not listed" is not just "not loaded yet".
    await waitFor(() => expect(result.current.contextLabel).toBe(productionTwo.name));
    expect(result.current.contextItems.some((item) => item.key === 'production-rehearsal')).toBe(true);
    expect(result.current.contextItems.some((item) => item.key === 'production-ticket')).toBe(false);
    expect(result.current.contextItems.some((item) => item.key === 'production-ticket-quota')).toBe(false);
  });

  /**
   * 担当者権限をメンバー管理へ統合・複数Role対応 §5: a delegate holding two
   * simultaneously-ACTIVE Roles must have both Roles' menu items enabled -
   * `delegate_roles` (array), not the legacy single-value `delegate_role`,
   * is what useNavMenu() now reads for this gating.
   */
  it('enables both メンバー管理 and チケット管理 for a delegate with two simultaneously-active Roles', async () => {
    const multiRoleProduction = {
      ...productionTwo,
      delegate_role: 'PARTICIPANT_MANAGER',
      delegate_roles: ['PARTICIPANT_MANAGER', 'TICKET_MANAGER'],
    };
    mockPathname = `/productions/${multiRoleProduction.id}`;
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [] },
      { test: (url) => url.endsWith(`/productions/${multiRoleProduction.id}`), status: 200, body: multiRoleProduction },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [] },
    ]);

    const { result } = await renderHook(() => useNavMenu(), { wrapper });

    await waitFor(() => expect(result.current.contextLabel).toBe(multiRoleProduction.name));
    expect(result.current.contextItems.some((item) => item.key === 'production-members')).toBe(true);
    expect(result.current.contextItems.some((item) => item.key === 'production-ticket')).toBe(true);
    // A Role not in delegate_roles is still not listed.
    expect(result.current.contextItems.some((item) => item.key === 'production-performances')).toBe(false);
  });

  /**
   * StageArt UI再構成 instruction (this round §「戻る・Context切替」):
   * Organization Context gets a static "← 所属団体一覧へ戻る" link (no
   * extra data fetch required). A dynamic Production -> Organization
   * link was attempted this round but reverted after it was found to
   * break ~19 unrelated Production Context tests (adding a new async
   * operation to the globally-shared useNavMenu() interfered with those
   * tests' own fireEvent-driven state) - see useNavMenu.ts's own
   * ORGANIZATION_BACK_TO docblock and this round's report. Production
   * Context's `backTo` therefore stays `null` for now, and no '/projects'
   * fetch should ever be attempted by useNavMenu() itself.
   */
  it('shows the static Organization -> 所属団体一覧 backTo link with no extra data dependency', async () => {
    mockPathname = `/organizations/${orgOne.id}`;
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [orgOne] },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
    ]);

    const { result } = await renderHook(() => useNavMenu(), { wrapper });

    await waitFor(() => expect(result.current.contextLabel).toBe(orgOne.name));
    expect(result.current.backTo?.href).toBe('/organizations');
    expect(result.current.backTo?.label).toContain('所属団体一覧');
  });

  it('has no backTo link in Production Context, and never attempts to fetch /projects', async () => {
    mockPathname = `/productions/${productionOne.id}`;
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [orgOne] },
      { test: (url) => url.endsWith(`/productions/${productionOne.id}`), status: 200, body: productionOne },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
    ]);

    const { result } = await renderHook(() => useNavMenu(), { wrapper });

    await waitFor(() => expect(result.current.contextLabel).toBe(productionOne.name));
    expect(result.current.backTo).toBeNull();
    expect((global.fetch as jest.Mock).mock.calls.some(([url]: [string]) => String(url).endsWith('/projects'))).toBe(false);
  });

  it('has no backTo link in Home Context', async () => {
    mockPathname = '/home';
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [] },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
    ]);

    const { result } = await renderHook(() => useNavMenu(), { wrapper });

    await waitFor(() => expect(result.current.contextType).toBe('home'));
    expect(result.current.backTo).toBeNull();
  });

  it('keeps Organization information as the single top-level entry and groups member/production operations', async () => {
    mockPathname = `/organizations/${orgOne.id}`;
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [orgOne] },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [] },
    ]);

    const { result } = await renderHook(() => useNavMenu(), { wrapper });

    await waitFor(() => expect(result.current.contextLabel).toBe(orgOne.name));
    expect(result.current.contextItems.find((item) => item.key === 'organization-info')?.groupLabel).toBeUndefined();
    expect(result.current.contextItems.find((item) => item.key === 'organization-members')?.groupLabel).toBe('メンバー管理');
    expect(result.current.contextItems.find((item) => item.key === 'organization-members-owner-transfer')?.label).toBe('代表者交代');
    expect(result.current.contextItems.find((item) => item.key === 'organization-productions')?.label).toBe('公演・活動一覧');
    expect(result.current.contextItems.find((item) => item.key === 'organization-productions')?.groupLabel).toBe('公演・活動管理');
    expect(result.current.contextItems.some((item) => item.key === 'organization-about')).toBe(false);
    expect(result.current.contextItems.some((item) => item.key === 'organization-sns')).toBe(false);
    expect(result.current.contextItems.some((item) => item.key === 'organization-links')).toBe(false);
    expect(result.current.contextItems.some((item) => item.key === 'organization-requests')).toBe(false);
    expect(result.current.contextItems.some((item) => item.key === 'organization-invite')).toBe(false);
    expect(result.current.contextItems.some((item) => item.key === 'organization-productions-create-past')).toBe(false);
  });


  it('switches contextItems correctly when navigating from Organization to Production and back to Home (context isolation)', async () => {
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [orgOne] },
      { test: (url) => url.endsWith(`/productions/${productionOne.id}`), status: 200, body: productionOne },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [projectOne] },
    ]);

    mockPathname = `/organizations/${orgOne.id}`;
    const { result, rerender } = await renderHook(() => useNavMenu(), { wrapper });
    await waitFor(() => expect(result.current.contextType).toBe('organization'));
    // 団体情報 depends on the caller's role, so wait for the Organization itself to load.
    await waitFor(() => expect(result.current.contextLabel).toBe(orgOne.name));
    expect(result.current.contextItems.some((item) => item.key === 'production-rehearsal')).toBe(false);
    expect(result.current.contextItems.some((item) => item.key === 'organization-info')).toBe(true);

    mockPathname = `/productions/${productionOne.id}`;
    rerender(undefined);
    await waitFor(() => expect(result.current.contextType).toBe('production'));
    // Production items are listed once the Production itself has loaded.
    await waitFor(() => expect(result.current.contextLabel).toBe(productionOne.name));
    expect(result.current.contextItems.some((item) => item.key === 'organization-info')).toBe(false);
    expect(result.current.contextItems.some((item) => item.key === 'production-rehearsal')).toBe(true);

    mockPathname = '/home';
    rerender(undefined);
    await waitFor(() => expect(result.current.contextType).toBe('home'));
    expect(result.current.contextItems.some((item) => item.key === 'production-rehearsal')).toBe(false);
    expect(result.current.contextItems.some((item) => item.key === 'organization-info')).toBe(false);
    expect(result.current.contextItems.some((item) => item.key === 'discover-organizations')).toBe(true);
  });
  /**
   * メニューの非表示統一: 公演終了／精算処理 follows Settlement.Manage on the
   * server (PrimaryManager or ACCOUNTING_MANAGER), so an ACCOUNTING_MANAGER
   * delegate gets it - and 会計 (予算 / 実績), which follows Accounting.Update
   * the same way - and nothing else they may not use.
   */
  it('lists 公演終了／精算処理 and 会計 for an ACCOUNTING_MANAGER delegate and hides the rest of the management items', async () => {
    const accountingDelegate = { ...productionTwo, delegate_role: 'ACCOUNTING_MANAGER', delegate_roles: ['ACCOUNTING_MANAGER'] };
    mockPathname = `/productions/${accountingDelegate.id}`;
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [] },
      { test: (url) => url.endsWith(`/productions/${accountingDelegate.id}`), status: 200, body: accountingDelegate },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [] },
    ]);

    const { result } = await renderHook(() => useNavMenu(), { wrapper });

    await waitFor(() => expect(result.current.contextLabel).toBe(accountingDelegate.name));
    expect(result.current.contextItems.map((item) => item.key)).toEqual([
      'production-rehearsal',
      'production-settlement',
      'production-accounting-budget',
      'production-accounting',
    ]);
  });

  /**
   * Switching from an Organization the Person owns to a Production where
   * they hold no Production Role must not carry any item over, and
   * coming back restores the Organization's own items.
   */
  it('recomputes the items when switching from an Organization to a Production and back', async () => {
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [orgOne] },
      { test: (url) => url.endsWith(`/productions/${productionTwo.id}`), status: 200, body: { ...productionTwo, is_primary_manager: false, delegate_role: null, delegate_roles: [] } },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [] },
    ]);

    mockPathname = `/organizations/${orgOne.id}`;
    const { result, rerender } = await renderHook(() => useNavMenu(), { wrapper });
    await waitFor(() => expect(result.current.contextLabel).toBe(orgOne.name));
    expect(result.current.contextItems.map((item) => item.key)).toContain('organization-members-owner-transfer');

    mockPathname = `/productions/${productionTwo.id}`;
    rerender(undefined);
    await waitFor(() => expect(result.current.contextLabel).toBe(productionTwo.name));
    expect(result.current.contextType).toBe('production');
    expect(result.current.contextItems.map((item) => item.key)).toEqual(['production-rehearsal']);

    mockPathname = `/organizations/${orgOne.id}`;
    rerender(undefined);
    await waitFor(() => expect(result.current.contextLabel).toBe(orgOne.name));
    expect(result.current.contextItems.every((item) => item.href.toString().startsWith(`/organizations/${orgOne.id}/`))).toBe(true);
    expect(result.current.contextItems.map((item) => item.key)).toContain('organization-members-owner-transfer');
  });
  /**
   * Someone outside the Production (the server refuses GET /productions/{id})
   * gets no Production item at all - not even 予定管理, which they could not open.
   */
  it('lists no Production item when the Production is refused and the Person does not participate in it', async () => {
    mockPathname = '/productions/prod-outside';
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [] },
      { test: (url) => url.endsWith('/productions/prod-outside'), status: 403, body: { code: 'stageart_production_access_denied', message: 'denied', data: { status: 403 } } },
      { test: (url) => url.endsWith('/me/participating-productions'), status: 200, body: [participatingRow('prod-other')] },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [] },
    ]);

    const { result } = await renderHook(() => useNavMenu(), { wrapper: appWrapper });

    expect(result.current.contextType).toBe('production');
    await waitFor(() => expect(result.current.contextLabel).toBe('公演'));
    await waitFor(() =>
      expect((global.fetch as jest.Mock).mock.calls.some(([url]) => String(url).endsWith('/me/participating-productions'))).toBe(true)
    );
    await new Promise((resolve) => setTimeout(resolve, 300));
    expect(result.current.contextItems).toEqual([]);
    expect(result.current.fixedItems.map((item) => item.key)).toEqual(['home', 'mypage', 'settings']);
  });
  /**
   * 参加者向け「予定管理」: a CAST/STAFF participant with no Production Role
   * cannot read GET /productions/{id} (403) but is listed by
   * GET /me/participating-productions - they get 予定管理 and nothing else.
   */
  it('lists only 予定管理 for a participant without a Production Role', async () => {
    mockPathname = '/production/prod-joined/schedule';
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [] },
      { test: (url) => url.endsWith('/productions/prod-joined'), status: 403, body: refused },
      { test: (url) => url.endsWith('/me/participating-productions'), status: 200, body: [participatingRow('prod-joined')] },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [] },
    ]);

    const { result } = await renderHook(() => useNavMenu(), { wrapper: appWrapper });

    await waitFor(() => expect(result.current.contextItems.map((item) => item.key)).toEqual(['production-rehearsal']));
    const schedule = result.current.contextItems[0];
    expect(schedule.label).toBe('予定管理');
    expect(schedule.href).toBe('/production/prod-joined/schedule/attendance');
  });

  it('asks nothing about participation for the PrimaryManager, who gets 予定管理 from the Production itself', async () => {
    mockPathname = `/productions/${productionOne.id}`;
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [] },
      { test: (url) => url.endsWith(`/productions/${productionOne.id}`), status: 200, body: productionOne },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [] },
    ]);

    const { result } = await renderHook(() => useNavMenu(), { wrapper });

    await waitFor(() => expect(result.current.contextLabel).toBe(productionOne.name));
    expect(result.current.contextItems.some((item) => item.key === 'production-rehearsal')).toBe(true);
    expect(result.current.contextItems.some((item) => item.key === 'production-info')).toBe(true);
    expect((global.fetch as jest.Mock).mock.calls.some(([url]) => String(url).endsWith('/me/participating-productions'))).toBe(false);
  });

  /**
   * Organization -> a Production the Person only participates in -> a
   * Production they are not part of -> back: each step shows only its own
   * items, nothing carried over.
   */
  it('recomputes the participant item across Organization / Production switches', async () => {
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [orgOne] },
      { test: (url) => url.endsWith('/productions/prod-joined'), status: 403, body: refused },
      { test: (url) => url.endsWith('/productions/prod-outside'), status: 403, body: refused },
      { test: (url) => url.endsWith('/me/participating-productions'), status: 200, body: [participatingRow('prod-joined')] },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [] },
    ]);

    mockPathname = `/organizations/${orgOne.id}`;
    const { result, rerender } = await renderHook(() => useNavMenu(), { wrapper: appWrapper });
    await waitFor(() => expect(result.current.contextLabel).toBe(orgOne.name));
    expect(result.current.contextItems.map((item) => item.key)).toContain('organization-members-owner-transfer');

    mockPathname = '/production/prod-joined/schedule';
    rerender(undefined);
    await waitFor(() => expect(result.current.contextItems.map((item) => item.key)).toEqual(['production-rehearsal']));
    expect(result.current.contextItems[0].href).toBe('/production/prod-joined/schedule/attendance');

    mockPathname = '/production/prod-outside/schedule';
    rerender(undefined);
    await waitFor(() => expect(result.current.contextType).toBe('production'));
    await waitFor(() =>
      expect((global.fetch as jest.Mock).mock.calls.some(([url]) => String(url).endsWith('/productions/prod-outside'))).toBe(true)
    );
    await new Promise((resolve) => setTimeout(resolve, 300));
    expect(result.current.contextItems).toEqual([]);

    mockPathname = `/organizations/${orgOne.id}`;
    rerender(undefined);
    await waitFor(() => expect(result.current.contextLabel).toBe(orgOne.name));
    expect(result.current.contextItems.some((item) => item.key.startsWith('production-'))).toBe(false);
    expect(result.current.contextItems.map((item) => item.key)).toContain('organization-members-owner-transfer');
  });
});
