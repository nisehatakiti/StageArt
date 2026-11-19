import { Tabs, useLocalSearchParams, useRouter, type Href } from 'expo-router';
import { Platform, StyleSheet, TouchableOpacity, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { ThemedText } from '@/components/themed-text';
import { Spacing } from '@/constants/theme';
import { canOpenProductionInvite, canViewProductionAccounting } from '@/features/production/permissions';
import { useProduction } from '@/features/production/useProductions';

/**
 * Production Context / Shell (§11, §15): no Role-based Navigation
 * branching in this file, except the entries whose content is itself
 * restricted (招待管理, and 会計 - Production-wide Budget / Actual are not
 * shown to a general member, ExpenseApprovalWorkflow.md "Accounting
 * Visibility"). Organization/Production themselves
 * intentionally do not get their own bottom-nav tab
 * (FrontendArchitecture.md - "「公演」「団体」は下部ナビゲーションに置か
 * ない"); Home (outside this layout entirely) is the cross-Production
 * entry point instead.
 *
 * Phase 6.2 adds a 4th tab, "お知らせ" (Notification Feed,
 * GET /productions/{id}/notifications). Neither ManagementNavigationPolicy.md
 * nor FrontendArchitecture.md fixes an exact Mobile tab count or names a
 * placement for this feed; the closest content it belongs to (Timetable
 * Version Publish events) is Production-scoped and used across a
 * session, matching Schedule/Accounting's existing tab treatment more
 * than a nested sub-view of either would. Disclosed as a judgment call,
 * not a Blueprint-mandated layout - see this Phase's report.
 *
 * §20 Back Navigation: an explicit "← Home" control rather than relying
 * only on the native Stack header/back-gesture - both because
 * FrontendArchitecture.md §59/§115 favors explicit, labeled affordances
 * for Accessibility, and because a native header back button rendered
 * by react-native-screens is not reliably interactable inside Jest, so
 * an explicit testID keeps §24's "Production → Homeへ戻る" test
 * deterministic rather than exercising native chrome.
 *
 * 参加者向け「公演概要ダッシュボード」instruction: "overview" (概要) is
 * declared LAST, not first - `<Tabs>` defaults to `backBehavior:
 * 'initialRoute'` (the FIRST declared Tabs.Screen), and every existing
 * entry point into this Shell (Home's Production card, Organization's
 * Production list, the Web Management sidebar's「稽古管理」, etc.) still
 * navigates straight to "schedule" - empirically confirmed that
 * declaring "overview" first breaks "← Home" (it would first jump to
 * "overview" instead of leaving the Shell; home-to-production.test.tsx
 * catches this). Only "参加している公演・活動"/Profile's own link targets
 * `/production/{id}/overview` directly, so it never depends on tab order.
 *
 * Phase 7.1 (ProductionTitleHeadingPolicy.md, explicitly names
 * "Mobile設計"): when `title_heading` is set, it renders above the
 * Production Title, never concatenated into it ("公演肩書は公演タイトルの
 * 一部として連結保存しない" - kept as two separate Text nodes here for
 * the same reason). Absent entirely when null - no placeholder space.
 */
export default function ProductionShellLayout() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();
  const { data: production } = useProduction(id);

  return (
    <View style={styles.container}>
      <SafeAreaView edges={['top']} style={styles.header}>
        <TouchableOpacity onPress={() => router.back()} testID="back-to-home">
          <ThemedText type="link">← Home</ThemedText>
        </TouchableOpacity>
        <View style={styles.titleColumn}>
          {production?.title_heading && (
            <ThemedText type="small" themeColor="textSecondary" numberOfLines={1} testID="production-title-heading">
              {production.title_heading}
            </ThemedText>
          )}
          <ThemedText type="subtitle" numberOfLines={1} style={styles.title} testID="production-title">
            {production?.name ?? 'Production'}
          </ThemedText>
        </View>
        {/* StageArt Web β版: 招待管理 (Join Key発行 + 参加申請承認) has no
            dedicated native Tab (adding one would change the existing,
            already-tested 4-tab native layout - see this file's own
            docblock on why native behavior stays untouched this phase),
            so this entry point is Web-only. It is listed only for those
            who may use the screen (PrimaryManager: 参加コード;
            PARTICIPANT_MANAGER: 参加申請) - features/production/permissions.ts. */}
        {Platform.OS === 'web' && canOpenProductionInvite(production) && (
          <TouchableOpacity onPress={() => router.push(`/production-invite/${id}` as Href)} testID="production-invite-link">
            <ThemedText type="link">招待管理</ThemedText>
          </TouchableOpacity>
        )}
      </SafeAreaView>

      <Tabs screenOptions={{ headerShown: false }}>
        <Tabs.Screen name="schedule" options={{ title: '予定' }} />
        <Tabs.Screen name="notifications" options={{ title: 'お知らせ' }} />
        {/* 会計 is listed only for the PrimaryManager or an ACCOUNTING_MANAGER
            (features/production/permissions.ts); while the Production is still
            loading or could not be read, it stays hidden. The screen checks
            again on its own and the API refuses everyone else (403). */}
        <Tabs.Screen
          name="accounting"
          options={{ title: '会計', href: canViewProductionAccounting(production) ? undefined : null }}
        />
        <Tabs.Screen name="mypage" options={{ title: 'マイページ' }} />
        <Tabs.Screen name="overview" options={{ title: '概要' }} />
      </Tabs>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: Spacing.three,
    paddingHorizontal: Spacing.four,
    paddingVertical: Spacing.two,
  },
  titleColumn: { flex: 1 },
  title: { flex: 1 },
});
