import { Tabs, useLocalSearchParams, useRouter, type Href } from 'expo-router';
import { Platform, StyleSheet, TouchableOpacity, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { ThemedText } from '@/components/themed-text';
import { Spacing } from '@/constants/theme';
import { useProduction } from '@/features/production/useProductions';

/**
 * Production Context / Shell (§11, §15): no Role-based Navigation
 * branching anywhere in this file. Organization/Production themselves
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
            so this entry point is Web-only. */}
        {Platform.OS === 'web' && (
          <TouchableOpacity onPress={() => router.push(`/production-invite/${id}` as Href)} testID="production-invite-link">
            <ThemedText type="link">招待管理</ThemedText>
          </TouchableOpacity>
        )}
      </SafeAreaView>

      <Tabs screenOptions={{ headerShown: false }}>
        <Tabs.Screen name="schedule" options={{ title: '予定' }} />
        <Tabs.Screen name="notifications" options={{ title: 'お知らせ' }} />
        <Tabs.Screen name="accounting" options={{ title: '会計' }} />
        <Tabs.Screen name="mypage" options={{ title: 'マイページ' }} />
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
