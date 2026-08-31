import { useRouter, type Href } from 'expo-router';
import { useState, type PropsWithChildren, type ReactNode } from 'react';
import { Alert, ScrollView, StyleSheet, TouchableOpacity, View } from 'react-native';

import { StageArtLogo } from '@/components/brand/StageArtLogo';
import { ThemedText } from '@/components/themed-text';
import { BrandColors, MaxContentWidth, Spacing } from '@/constants/theme';
import { useOrganizationContext } from '@/features/organization/OrganizationContext';
import { useOrganizations } from '@/features/organization/useOrganizations';
import { useLogout } from '@/features/mypage/useLogout';
import { useCurrentPerson } from '@/features/person/useCurrentPerson';

/**
 * StageArt Web版 再設計 Phase 1: the PC-browser navigation shell this
 * whole redesign is anchored on - sidebar (global sections + the
 * currently-selected Organization's own submenu) + header (brand + user
 * menu) + breadcrumb trail, replacing AppShell's bottom-nav-as-primary-
 * navigation for every Web management screen this phase adds.
 *
 * Deliberately a NEW component, not a modification of AppShell: every
 * existing mobile-rn screen (native and the pre-existing Web screens
 * alike) keeps using AppShell completely unchanged - this redesign's own
 * explicit instruction is "モバイルUIをそのままWebにしない", which cuts
 * both ways: it also means not forcing this new Web-only chrome onto
 * screens this phase didn't touch. Only the new route files this phase
 * adds (Organization/Production management, Dashboard, the Profile
 * rebuild) import WebLayout.
 *
 * The Organization submenu's items are exactly this Organization's own
 * management surface (§5 of the redesign report) - Role-gated by
 * `current_person_role` (already present on every `Organization` list
 * item from GET /organizations, the same field home.tsx already reads
 * for its own OWNER-only invite link), never a new client-side
 * permission concept.
 */
export type Breadcrumb = { label: string; href?: Href };

type WebLayoutProps = PropsWithChildren<{
  breadcrumbs: Breadcrumb[];
  activeTopLevel: 'dashboard' | 'organizations' | 'productions' | 'profile' | null;
  /** When set, renders this Organization's own management submenu below
   * the global sidebar sections - only meaningful on
   * Organization/Production management screens. */
  organizationId?: string;
  /** When set, renders this Production's own management submenu instead
   * of (or in addition to) the Organization one. */
  productionId?: string;
  productionName?: string;
  headerExtra?: ReactNode;
}>;

const TOP_LEVEL_ITEMS = [
  { key: 'dashboard' as const, label: 'Dashboard', href: '/dashboard' as Href },
  { key: 'organizations' as const, label: '団体', href: '/organizations' as Href },
  { key: 'productions' as const, label: '公演・活動', href: '/participating-productions' as Href },
  { key: 'profile' as const, label: 'マイページ', href: '/profile' as Href },
];

export function WebLayout({
  children,
  breadcrumbs,
  activeTopLevel,
  organizationId,
  productionId,
  productionName,
  headerExtra,
}: WebLayoutProps) {
  const router = useRouter();
  const logout = useLogout();
  const currentPersonQuery = useCurrentPerson();
  const organizationsQuery = useOrganizations();
  const { currentOrganizationId, selectOrganization } = useOrganizationContext();
  const [orgPickerOpen, setOrgPickerOpen] = useState(false);

  const activeOrganizationId = organizationId ?? currentOrganizationId ?? undefined;
  const activeOrganization = organizationsQuery.data?.find((org) => org.id === activeOrganizationId) ?? null;
  const isOwner = activeOrganization?.current_person_role === 'OWNER';

  function handleLogout() {
    Alert.alert('ログアウト', 'ログアウトしますか？', [
      { text: 'キャンセル', style: 'cancel' },
      { text: 'ログアウト', style: 'destructive', onPress: () => logout() },
    ]);
  }

  return (
    <View style={styles.root}>
      <View style={styles.header} testID="web-header">
        <TouchableOpacity
          testID="web-header-logo"
          onPress={() => router.push('/dashboard' as Href)}
          style={styles.headerLogo}
          accessibilityRole="button"
          accessibilityLabel="StageArt Dashboardへ"
        >
          <StageArtLogo width={120} height={36} />
        </TouchableOpacity>
        <View style={styles.headerRight}>
          {headerExtra}
          <ThemedText type="small" style={styles.headerUser} testID="web-header-user">
            {currentPersonQuery.data ? [currentPersonQuery.data.family_name, currentPersonQuery.data.given_name].filter(Boolean).join(' ') : ''}
          </ThemedText>
          <TouchableOpacity onPress={handleLogout} testID="web-header-logout">
            <ThemedText type="link">ログアウト</ThemedText>
          </TouchableOpacity>
        </View>
      </View>

      <View style={styles.body}>
        <ScrollView style={styles.sidebar} contentContainerStyle={styles.sidebarContent} testID="web-sidebar">
          {TOP_LEVEL_ITEMS.map((item) => (
            <TouchableOpacity
              key={item.key}
              testID={`web-nav-${item.key}`}
              onPress={() => router.push(item.href)}
              style={[styles.navItem, activeTopLevel === item.key && styles.navItemActive]}
            >
              <ThemedText type="default" style={activeTopLevel === item.key ? styles.navLabelActive : styles.navLabel}>
                {item.label}
              </ThemedText>
            </TouchableOpacity>
          ))}

          <View style={styles.sidebarDivider} />

          <TouchableOpacity
            testID="web-org-selector"
            onPress={() => setOrgPickerOpen((open) => !open)}
            style={styles.orgSelector}
          >
            <ThemedText type="small" themeColor="textSecondary">
              現在の団体
            </ThemedText>
            <ThemedText type="smallBold" numberOfLines={1}>
              {activeOrganization ? `${activeOrganization.name} ▾` : '未選択 ▾'}
            </ThemedText>
          </TouchableOpacity>

          {orgPickerOpen && (
            <View testID="web-org-picker" style={styles.orgPicker}>
              {(organizationsQuery.data ?? []).map((org) => (
                <TouchableOpacity
                  key={org.id}
                  testID={`web-org-picker-${org.id}`}
                  onPress={() => {
                    selectOrganization(org.id);
                    setOrgPickerOpen(false);
                    router.push(`/organizations/${org.id}` as Href);
                  }}
                  style={styles.orgPickerItem}
                >
                  <ThemedText type="small">{org.name}</ThemedText>
                </TouchableOpacity>
              ))}
              <TouchableOpacity
                testID="web-org-picker-create"
                onPress={() => {
                  setOrgPickerOpen(false);
                  router.push('/organizations/create' as Href);
                }}
                style={styles.orgPickerItem}
              >
                <ThemedText type="small" themeColor="textSecondary">
                  ＋ 団体を作る
                </ThemedText>
              </TouchableOpacity>
            </View>
          )}

          {activeOrganizationId && (
            <View testID="web-org-submenu" style={styles.submenu}>
              <SubmenuLink href={`/organizations/${activeOrganizationId}` as Href} label="概要" testID="web-org-submenu-overview" />
              {isOwner && (
                <SubmenuLink href={`/organizations/${activeOrganizationId}/edit` as Href} label="団体情報編集" testID="web-org-submenu-edit" />
              )}
              <SubmenuLink href={`/organizations/${activeOrganizationId}/members` as Href} label="メンバー" testID="web-org-submenu-members" />
              {isOwner && (
                <SubmenuLink
                  href={`/organizations/${activeOrganizationId}/membership-requests` as Href}
                  label="参加申請"
                  testID="web-org-submenu-requests"
                />
              )}
              {isOwner && (
                <SubmenuLink href={`/organizations/${activeOrganizationId}/invite` as Href} label="招待" testID="web-org-submenu-invite" />
              )}
              <SubmenuLink
                href={`/organizations/${activeOrganizationId}/productions` as Href}
                label="公演"
                testID="web-org-submenu-productions"
              />
            </View>
          )}

          {productionId && (
            <View testID="web-production-submenu" style={styles.submenu}>
              <ThemedText type="small" themeColor="textSecondary" style={styles.submenuHeading} numberOfLines={1}>
                {productionName ?? '公演'}
              </ThemedText>
              <SubmenuLink href={`/productions/${productionId}` as Href} label="概要" testID="web-prod-submenu-overview" />
              <SubmenuLink href={`/productions/${productionId}/edit` as Href} label="公演情報編集" testID="web-prod-submenu-edit" />
              <SubmenuLink href={`/productions/${productionId}/participants` as Href} label="出演者" testID="web-prod-submenu-participants" />
              <SubmenuLink href={`/production/${productionId}/schedule` as Href} label="スケジュール" testID="web-prod-submenu-schedule" />
              <SubmenuLink
                href={`/production/${productionId}/schedule/attendance` as Href}
                label="出欠"
                testID="web-prod-submenu-attendance"
              />
              <SubmenuLink href={`/production/${productionId}/accounting` as Href} label="会計" testID="web-prod-submenu-accounting" />
              <SubmenuLink href={`/production/${productionId}/notifications` as Href} label="通知" testID="web-prod-submenu-notifications" />
              <SubmenuLink href={`/productions/${productionId}/publish` as Href} label="公開設定" testID="web-prod-submenu-publish" />
            </View>
          )}
        </ScrollView>

        <ScrollView style={styles.main} contentContainerStyle={styles.mainContent}>
          <View style={styles.breadcrumbRow} testID="web-breadcrumb">
            {breadcrumbs.map((crumb, index) => (
              <View key={index} style={styles.breadcrumbItem}>
                {index > 0 && (
                  <ThemedText type="small" themeColor="textSecondary" style={styles.breadcrumbSeparator}>
                    ›
                  </ThemedText>
                )}
                {crumb.href ? (
                  <TouchableOpacity onPress={() => router.push(crumb.href as Href)}>
                    <ThemedText type="link">{crumb.label}</ThemedText>
                  </TouchableOpacity>
                ) : (
                  <ThemedText type="small" themeColor="textSecondary">
                    {crumb.label}
                  </ThemedText>
                )}
              </View>
            ))}
          </View>

          <View style={styles.pageContent}>{children}</View>
        </ScrollView>
      </View>
    </View>
  );
}

function SubmenuLink({ href, label, testID }: { href: Href; label: string; testID: string }) {
  const router = useRouter();
  return (
    <TouchableOpacity testID={testID} onPress={() => router.push(href)} style={styles.submenuItem}>
      <ThemedText type="small">{label}</ThemedText>
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: '#F7F5F1' },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: Spacing.four,
    paddingVertical: Spacing.two,
    backgroundColor: BrandColors.blackoutBlack,
  },
  headerLogo: { paddingVertical: Spacing.one },
  headerRight: { flexDirection: 'row', alignItems: 'center', gap: Spacing.three },
  headerUser: { color: BrandColors.stageWarmWhite },
  body: { flex: 1, flexDirection: 'row' },
  sidebar: {
    width: 220,
    borderRightWidth: StyleSheet.hairlineWidth,
    borderRightColor: '#e1dee6',
    backgroundColor: '#FFFFFF',
  },
  sidebarContent: { paddingVertical: Spacing.three },
  navItem: { paddingHorizontal: Spacing.four, paddingVertical: Spacing.two },
  navItemActive: { backgroundColor: '#F7E4DE' },
  navLabel: { color: '#2A2320' },
  navLabelActive: { color: BrandColors.warmAmber, fontWeight: '700' },
  sidebarDivider: { height: StyleSheet.hairlineWidth, backgroundColor: '#e1dee6', marginVertical: Spacing.two },
  orgSelector: { paddingHorizontal: Spacing.four, paddingVertical: Spacing.two, gap: Spacing.half },
  orgPicker: { paddingBottom: Spacing.two },
  orgPickerItem: { paddingHorizontal: Spacing.five, paddingVertical: Spacing.one },
  submenu: { paddingTop: Spacing.two },
  submenuHeading: { paddingHorizontal: Spacing.four, paddingBottom: Spacing.one },
  submenuItem: { paddingHorizontal: Spacing.five, paddingVertical: Spacing.one },
  main: { flex: 1 },
  mainContent: { padding: Spacing.four, maxWidth: MaxContentWidth + 200, width: '100%' },
  breadcrumbRow: { flexDirection: 'row', flexWrap: 'wrap', marginBottom: Spacing.three },
  breadcrumbItem: { flexDirection: 'row', alignItems: 'center' },
  breadcrumbSeparator: { marginHorizontal: Spacing.one },
  pageContent: { gap: Spacing.four },
});
