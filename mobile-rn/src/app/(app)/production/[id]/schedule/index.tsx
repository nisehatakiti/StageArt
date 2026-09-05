import { useLocalSearchParams, useRouter } from 'expo-router';
import { useMemo, useState } from 'react';
import { ActivityIndicator, FlatList, RefreshControl, StyleSheet, TouchableOpacity } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { ScheduleItemCard } from '@/components/schedule-item-card';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';
import { defaultScheduleRange } from '@/features/schedule/dateRange';
import { formatDayHeader, groupByDay } from '@/features/schedule/groupByDay';
import { deriveMyParticipantTypes, relatesToMe } from '@/features/schedule/personalHighlight';
import { useParticipants, useProductionSchedule } from '@/features/schedule/useProductionSchedule';
import { useCurrentPerson } from '@/features/person/useCurrentPerson';
import { getErrorMessage } from '@/utils/errorMessage';

/**
 * Production Schedule (§2/§8): Production全体の共有進行表, Published
 * Version only, no Role-based filtering anywhere in this screen. §9's
 * "当日+翌日" is the default; §10's Full Range toggle switches to the
 * entire Production period by omitting from/to entirely.
 */
export default function ScheduleListScreen() {
  const { id: productionId } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();
  const [fullRange, setFullRange] = useState(false);

  const range = useMemo(() => (fullRange ? undefined : defaultScheduleRange()), [fullRange]);

  const itemsQuery = useProductionSchedule(productionId, range);
  const participantsQuery = useParticipants(productionId);
  const currentPersonQuery = useCurrentPerson();

  const myPersonId = currentPersonQuery.data?.id ?? null;
  const myParticipantTypes = useMemo(
    () => (myPersonId && participantsQuery.data ? deriveMyParticipantTypes(participantsQuery.data, myPersonId) : new Set<string>()),
    [myPersonId, participantsQuery.data]
  );

  const grouped = useMemo(() => groupByDay(itemsQuery.data ?? []), [itemsQuery.data]);

  return (
    <SafeAreaView style={styles.safeArea}>
      <ThemedView style={styles.header}>
        <ThemedView style={styles.versionBadge} testID="published-badge">
          <ThemedText type="small">確定版（Published）のみ表示中</ThemedText>
        </ThemedView>
        <TouchableOpacity
          onPress={() => setFullRange((v) => !v)}
          testID="range-toggle"
          accessibilityRole="button"
          accessibilityLabel={fullRange ? '当日＋翌日のみ表示に切り替え' : '全期間を表示に切り替え'}
        >
          <ThemedText type="link">{fullRange ? '当日＋翌日のみ表示' : '全期間を表示'}</ThemedText>
        </TouchableOpacity>
        <TouchableOpacity
          onPress={() => router.push(`/production/${productionId}/schedule/print`)}
          testID="schedule-print-link"
          accessibilityRole="button"
          accessibilityLabel="印刷"
        >
          <ThemedText type="link">印刷</ThemedText>
        </TouchableOpacity>
        <TouchableOpacity
          onPress={() => router.push(`/production/${productionId}/schedule/attendance`)}
          testID="schedule-attendance-link"
          accessibilityRole="button"
          accessibilityLabel="出欠"
        >
          <ThemedText type="link">出欠</ThemedText>
        </TouchableOpacity>
      </ThemedView>

      {itemsQuery.isLoading && (
        <ThemedView style={styles.centered}>
          <ActivityIndicator />
        </ThemedView>
      )}

      {itemsQuery.isError && (
        <ThemedView style={styles.centered}>
          <ThemedText testID="schedule-error">{getErrorMessage(itemsQuery.error)}</ThemedText>
        </ThemedView>
      )}

      {!itemsQuery.isLoading && !itemsQuery.isError && grouped.length === 0 && (
        <ThemedView style={styles.centered}>
          <ThemedText testID="schedule-empty">
            {fullRange ? 'このProductionにはまだ予定がありません。' : 'この期間に予定はありません。'}
          </ThemedText>
          {!fullRange && (
            <TouchableOpacity onPress={() => setFullRange(true)} testID="schedule-empty-show-full-range">
              <ThemedText type="link">全期間を表示する</ThemedText>
            </TouchableOpacity>
          )}
        </ThemedView>
      )}

      {!itemsQuery.isLoading && !itemsQuery.isError && grouped.length > 0 && (
        <FlatList
          testID="schedule-day-list"
          data={grouped}
          keyExtractor={(day) => day.dayKey}
          refreshControl={
            <RefreshControl
              refreshing={itemsQuery.isFetching}
              onRefresh={itemsQuery.refetch}
              testID="schedule-refresh-control"
            />
          }
          contentContainerStyle={styles.list}
          renderItem={({ item: day }) => (
            <ThemedView style={styles.daySection}>
              <ThemedView style={styles.dayHeader}>
                <ThemedText type="subtitle">{formatDayHeader(day.date)}</ThemedText>
              </ThemedView>
              {day.items.map((item) => (
                <ScheduleItemCard
                  key={item.id}
                  item={item}
                  highlighted={relatesToMe(item, myPersonId, myParticipantTypes)}
                  onPress={() => router.push(`/production/${productionId}/schedule/${item.id}`)}
                />
              ))}
            </ThemedView>
          )}
        />
      )}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safeArea: { flex: 1 },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: Spacing.three,
    paddingVertical: Spacing.two,
  },
  versionBadge: {
    paddingHorizontal: Spacing.two,
    paddingVertical: 2,
    borderRadius: 999,
    backgroundColor: '#eeecf3',
  },
  centered: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: Spacing.four, gap: Spacing.two },
  list: { paddingHorizontal: Spacing.three, paddingBottom: Spacing.four },
  daySection: { marginBottom: Spacing.three },
  dayHeader: { paddingVertical: Spacing.two },
});
