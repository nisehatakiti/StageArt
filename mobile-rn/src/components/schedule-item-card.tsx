import { StyleSheet, TouchableOpacity, View } from 'react-native';

import { Spacing } from '@/constants/theme';
import { formatTime } from '@/features/schedule/groupByDay';
import type { TimetableItem } from '@/types/api';

import { ThemedText } from './themed-text';

/** §16: "Venue = 舞台" is the existing Stage Usage signal (no dedicated
 * Boolean field). Any non-empty Venue is shown for context; an exact
 * "舞台" match gets an additional, more prominent Stage Usage badge -
 * shown to every viewer regardless of Role (§17 Stage Usage Visibility,
 * mirrors the Flutter reference implementation's `hasVenue` badge, plus
 * the instruction's explicit "[舞台]" emphasis example). */
const STAGE_VENUE = '舞台';

export function ScheduleItemCard({
  item,
  highlighted,
  onPress,
}: {
  item: TimetableItem;
  highlighted: boolean;
  onPress: () => void;
}) {
  const isStageUsage = item.venue === STAGE_VENUE;

  return (
    <TouchableOpacity
      style={[styles.card, highlighted && styles.highlighted]}
      onPress={onPress}
      testID={`schedule-item-${item.id}`}
      accessibilityRole="button"
      accessibilityLabel={`${formatTime(item.start_date_time)} ${item.title}`}
    >
      <View style={styles.timeRow}>
        <ThemedText type="smallBold">
          {formatTime(item.start_date_time)}
          {item.end_date_time ? ` - ${formatTime(item.end_date_time)}` : ''}
        </ThemedText>
        {highlighted && (
          <View style={styles.highlightBadge} testID={`highlight-badge-${item.id}`}>
            <ThemedText type="small" style={styles.highlightBadgeText}>
              あなたに関係する予定
            </ThemedText>
          </View>
        )}
      </View>

      <ThemedText type="default">{item.title}</ThemedText>

      {item.description && (
        <ThemedText type="small" themeColor="textSecondary">
          {item.description}
        </ThemedText>
      )}

      <View style={styles.badgeRow}>
        {item.category && (
          <View style={styles.badge} testID={`category-badge-${item.id}`}>
            <ThemedText type="small">{item.category}</ThemedText>
          </View>
        )}

        {isStageUsage && (
          <View style={[styles.badge, styles.stageBadge]} testID={`stage-usage-badge-${item.id}`}>
            <ThemedText type="small">[舞台]</ThemedText>
          </View>
        )}

        {!isStageUsage && item.venue && (
          <View style={styles.badge} testID={`venue-badge-${item.id}`}>
            <ThemedText type="small">{item.venue}</ThemedText>
          </View>
        )}

        {item.participant_type && (
          <View style={styles.badge} testID={`participant-type-badge-${item.id}`}>
            <ThemedText type="small">Role: {item.participant_type}</ThemedText>
          </View>
        )}

        {item.target_person_ids.length > 0 && (
          <View style={styles.badge} testID={`target-person-badge-${item.id}`}>
            <ThemedText type="small">対象: {item.target_person_ids.length}名</ThemedText>
          </View>
        )}
      </View>
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  card: {
    padding: Spacing.three,
    borderWidth: 1,
    borderColor: '#e1dee6',
    borderRadius: 10,
    marginBottom: Spacing.two,
    gap: Spacing.half,
  },
  highlighted: {
    borderColor: '#4a3f7a',
    borderWidth: 2,
    backgroundColor: '#ece9f5',
  },
  timeRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: Spacing.two },
  highlightBadge: {
    paddingHorizontal: Spacing.two,
    paddingVertical: 2,
    borderRadius: 999,
    backgroundColor: '#4a3f7a',
  },
  highlightBadgeText: { color: '#ffffff' },
  badgeRow: { flexDirection: 'row', flexWrap: 'wrap', gap: Spacing.one, marginTop: Spacing.one },
  badge: {
    paddingHorizontal: Spacing.two,
    paddingVertical: 2,
    borderRadius: 999,
    backgroundColor: '#eeecf3',
  },
  stageBadge: {
    backgroundColor: '#f5ecdb',
  },
});
