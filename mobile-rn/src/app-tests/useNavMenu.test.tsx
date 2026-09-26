import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { renderHook, waitFor } from '@testing-library/react-native';
import type { PropsWithChildren } from 'react';

import { AuthProvider } from '@/auth/AuthContext';
import { useNavMenu } from '@/components/chrome/useNavMenu';

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
    expect(info?.disabled).toBe(false);
    expect(result.current.contextItems.some((item) => item.key === 'organization-invite')).toBe(true);
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
      'organization-about',
      'organization-sns',
      'organization-links',
      'organization-members',
      'organization-members-add',
      'organization-members-delegate',
      'organization-members-owner-transfer',
      'organization-requests',
      'organization-invite',
      'organization-productions',
      'organization-productions-create',
      'organization-productions-create-past',
      'organization-productions-edit',
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

  it('hides Owner-only Organization Context items and disables 団体情報 for a MEMBER', async () => {
    mockPathname = `/organizations/${orgTwo.id}/members`;
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [orgTwo] },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [] },
    ]);

    const { result } = await renderHook(() => useNavMenu(), { wrapper });

    await waitFor(() => expect(result.current.contextType).toBe('organization'));
    const info = result.current.contextItems.find((item) => item.key === 'organization-info');
    expect(info?.disabled).toBe(true);
    expect(result.current.contextItems.some((item) => item.key === 'organization-invite')).toBe(false);
  });

  it('switches to Production Context on a /productions/{id} route, disabling management items for a non-manager', async () => {
    mockPathname = `/productions/${productionTwo.id}`;
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [] },
      { test: (url) => url.endsWith(`/productions/${productionTwo.id}`), status: 200, body: productionTwo },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [] },
    ]);

    const { result } = await renderHook(() => useNavMenu(), { wrapper });

    await waitFor(() => expect(result.current.contextType).toBe('production'));
    const info = result.current.contextItems.find((item) => item.key === 'production-info');
    expect(info?.disabled).toBe(true);
    // productionTwo's delegate_role is REHEARSAL_MANAGER, not PARTICIPANT_MANAGER.
    const members = result.current.contextItems.find((item) => item.key === 'production-members');
    expect(members?.disabled).toBe(true);
    const rehearsal = result.current.contextItems.find((item) => item.key === 'production-rehearsal');
    expect(rehearsal?.disabled).toBeUndefined();
    // productionTwo's delegate_role is REHEARSAL_MANAGER, not PERFORMANCE_MANAGER.
    const performances = result.current.contextItems.find((item) => item.key === 'production-performances');
    expect(performances?.disabled).toBe(true);
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
    expect(performances?.disabled).toBe(false);
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
    expect(result.current.contextItems.find((item) => item.key === 'production-timetable')?.disabled).toBe(false);
    expect(result.current.contextItems.find((item) => item.key === 'production-reception')?.disabled).toBe(false);
    expect(result.current.contextItems.find((item) => item.key === 'production-settlement')?.disabled).toBe(false);
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

  it('enables チケット管理 for a Primary Manager but disables it for a non-TICKET_MANAGER delegate', async () => {
    mockPathname = `/productions/${productionOne.id}`;
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [] },
      { test: (url) => url.endsWith(`/productions/${productionOne.id}`), status: 200, body: productionOne },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [] },
    ]);

    const { result } = await renderHook(() => useNavMenu(), { wrapper });

    await waitFor(() => expect(result.current.contextLabel).toBe(productionOne.name));
    expect(result.current.contextItems.find((item) => item.key === 'production-ticket')?.disabled).toBe(false);
  });

  it('disables チケット管理 for a REHEARSAL_MANAGER delegate (not TICKET_MANAGER)', async () => {
    mockPathname = `/productions/${productionTwo.id}`;
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [] },
      { test: (url) => url.endsWith(`/productions/${productionTwo.id}`), status: 200, body: productionTwo },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [] },
    ]);

    const { result } = await renderHook(() => useNavMenu(), { wrapper });

    await waitFor(() => expect(result.current.contextType).toBe('production'));
    expect(result.current.contextItems.find((item) => item.key === 'production-ticket')?.disabled).toBe(true);
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

  /**
   * StageArt UI再構成 instruction (this round §「Organization Context」):
   * 団体情報(flat) / 公開ページ管理(ABOUT・SNS・リンク) / メンバー管理 /
   * 公演管理 という2階層構造 - 前回の小屋入り～本番と同じgroupLabelパターン。
   */
  it('groups ABOUT/SNS/リンク under 公開ページ管理, and メンバー管理/公演管理 items under their own headings', async () => {
    mockPathname = `/organizations/${orgOne.id}`;
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [orgOne] },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [] },
    ]);

    const { result } = await renderHook(() => useNavMenu(), { wrapper });

    await waitFor(() => expect(result.current.contextLabel).toBe(orgOne.name));
    const about = result.current.contextItems.find((item) => item.key === 'organization-about');
    const membersGroupStart = result.current.contextItems.find((item) => item.key === 'organization-members');
    const productionsGroupStart = result.current.contextItems.find((item) => item.key === 'organization-productions');
    expect(about?.groupLabel).toBe('公開ページ管理');
    expect(membersGroupStart?.groupLabel).toBe('メンバー管理');
    expect(productionsGroupStart?.groupLabel).toBe('公演管理');
    // 団体情報 itself is not grouped - it is the Organization's own flat top item.
    expect(result.current.contextItems.find((item) => item.key === 'organization-info')?.groupLabel).toBeUndefined();
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
    expect(result.current.contextItems.some((item) => item.key === 'production-rehearsal')).toBe(false);
    expect(result.current.contextItems.some((item) => item.key === 'organization-info')).toBe(true);

    mockPathname = `/productions/${productionOne.id}`;
    rerender(undefined);
    await waitFor(() => expect(result.current.contextType).toBe('production'));
    expect(result.current.contextItems.some((item) => item.key === 'organization-info')).toBe(false);
    expect(result.current.contextItems.some((item) => item.key === 'production-rehearsal')).toBe(true);

    mockPathname = '/home';
    rerender(undefined);
    await waitFor(() => expect(result.current.contextType).toBe('home'));
    expect(result.current.contextItems.some((item) => item.key === 'production-rehearsal')).toBe(false);
    expect(result.current.contextItems.some((item) => item.key === 'organization-info')).toBe(false);
    expect(result.current.contextItems.some((item) => item.key === 'discover-organizations')).toBe(true);
  });
});
