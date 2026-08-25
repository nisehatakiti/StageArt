import { useLocalSearchParams, useRouter } from 'expo-router';
import { ActivityIndicator, FlatList, StyleSheet, TouchableOpacity } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';
import { formatDayHeader } from '@/features/schedule/groupByDay';
import { useRehearsals } from '@/features/attendance/useRehearsals';
import { getErrorMessage } from '@/utils/errorMessage';

/**
 * Rehearsal list, entry point for Attendance (§17-19). A separate
 * screen from Schedule's TimetableItem list rather than reusing it -
 * TimetableItem carries no rehearsal_id (see schedule/[itemId].tsx's
 * own Phase 5.3 disclosure), so Attendance is built against
 * GET /productions/{id}/rehearsals directly instead.
 */
export default function RehearsalListScreen() {
  const { id: productionId } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();
  const rehearsalsQuery = useRehearsals(productionId);

  return (
    <SafeAreaView style={styles.safeArea}>
      <ThemedText type="title" style={styles.title}>
        出欠
      </ThemedText>

      {/* docs/04-HomeRoleBasedMenu.md §07の稽古管理「日程作成・調整」 - 権限
          チェックはClient側で複製しない (§24/§25と同じ既存方針)。管理権限
          のないPersonが押した場合は、作成APIが返す403をそのまま表示する。 */}
      <TouchableOpacity
        testID="rehearsal-create-link"
        onPress={() => router.push(`/production/${productionId}/schedule/attendance/create`)}
        style={styles.createLink}
      >
        <ThemedText type="link">＋ 稽古を作成する</ThemedText>
      </TouchableOpacity>

      {rehearsalsQuery.isLoading && (
        <ThemedView style={styles.centered}>
          <ActivityIndicator testID="rehearsal-list-loading" />
        </ThemedView>
      )}

      {rehearsalsQuery.isError && (
        <ThemedView style={styles.centered}>
          <ThemedText testID="rehearsal-list-error">{getErrorMessage(rehearsalsQuery.error)}</ThemedText>
        </ThemedView>
      )}

      {!rehearsalsQuery.isLoading && !rehearsalsQuery.isError && (rehearsalsQuery.data?.length ?? 0) === 0 && (
        <ThemedView style={styles.centered}>
          <ThemedText testID="rehearsal-list-empty" themeColor="textSecondary">
            稽古の予定はまだありません。
          </ThemedText>
        </ThemedView>
      )}

      {!rehearsalsQuery.isLoading && !rehearsalsQuery.isError && (rehearsalsQuery.data?.length ?? 0) > 0 && (
        <FlatList
          testID="rehearsal-list"
          data={rehearsalsQuery.data}
          keyExtractor={(rehearsal) => rehearsal.id}
          contentContainerStyle={styles.list}
          renderItem={({ item: rehearsal }) => (
            <TouchableOpacity
              testID={`rehearsal-row-${rehearsal.id}`}
              style={styles.row}
              onPress={() => router.push(`/production/${productionId}/schedule/attendance/${rehearsal.id}`)}
            >
              <ThemedText type="smallBold">{rehearsal.title ?? '無題の稽古'}</ThemedText>
              {rehearsal.start_date_time && <ThemedText type="small">{formatDayHeader(new Date(rehearsal.start_date_time))}</ThemedText>}
            </TouchableOpacity>
          )}
        />
      )}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safeArea: { flex: 1 },
  title: { fontSize: 24, lineHeight: 30, paddingHorizontal: Spacing.four, paddingTop: Spacing.three },
  createLink: { paddingHorizontal: Spacing.four, paddingTop: Spacing.two },
  centered: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: Spacing.four, gap: Spacing.two },
  list: { padding: Spacing.four, gap: Spacing.two },
  row: {
    borderWidth: 1,
    borderColor: '#e1dee6',
    borderRadius: 10,
    padding: Spacing.three,
    gap: 2,
  },
});
