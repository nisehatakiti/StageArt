import { useLocalSearchParams } from 'expo-router';
import { ActivityIndicator, FlatList, RefreshControl, StyleSheet, TouchableOpacity } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';
import { useMarkNotificationRead, useProductionNotifications } from '@/features/notifications/useNotifications';
import { buildNotificationViewModel, type NotificationViewModel } from '@/features/notifications/viewModel';
import { getErrorMessage } from '@/utils/errorMessage';

/**
 * Phase 6.2: Production Context "お知らせ" tab, backed by
 * GET /productions/{id}/notifications (Backend Phase 3.5). Only shows
 * TimetableVersionPublished Facts - the only Notification kind the
 * Backend produces today - no pagination (the endpoint has none).
 *
 * Phase 7.1 (NotificationPolicy.md's "未読 / 既読", Backend Phase 7.0):
 * tapping a row marks it read via PATCH /notifications/{id}/read.
 * Blueprint does not specify a Mobile mark-read trigger explicitly - tap
 * was chosen as the least-invented option (the existing row was already
 * the only interactive surface a Notification has; no Detail screen or
 * navigation target exists to route to instead - see this Phase's
 * report). `is_read` is never set locally before the Backend confirms
 * it - the row reflects only what the last successful fetch returned.
 */
export default function NotificationsScreen() {
  const { id: productionId } = useLocalSearchParams<{ id: string }>();
  const notificationsQuery = useProductionNotifications(productionId);
  const markRead = useMarkNotificationRead(productionId);
  const items = notificationsQuery.data?.map(buildNotificationViewModel) ?? [];

  return (
    <SafeAreaView style={styles.safeArea}>
      {notificationsQuery.isLoading && (
        <ThemedView style={styles.centered}>
          <ActivityIndicator testID="notifications-loading" />
        </ThemedView>
      )}

      {notificationsQuery.isError && (
        <ThemedView style={styles.centered}>
          <ThemedText testID="notifications-error">{getErrorMessage(notificationsQuery.error)}</ThemedText>
          <TouchableOpacity
            onPress={() => notificationsQuery.refetch()}
            testID="notifications-retry"
            accessibilityRole="button"
            accessibilityLabel="再読み込み"
          >
            <ThemedText type="link">再読み込み</ThemedText>
          </TouchableOpacity>
        </ThemedView>
      )}

      {!notificationsQuery.isLoading && !notificationsQuery.isError && items.length === 0 && (
        <ThemedView style={styles.centered}>
          <ThemedText testID="notifications-empty" themeColor="textSecondary">
            お知らせはまだありません。
          </ThemedText>
        </ThemedView>
      )}

      {!notificationsQuery.isLoading && !notificationsQuery.isError && items.length > 0 && (
        <FlatList
          testID="notifications-list"
          data={items}
          keyExtractor={(item) => item.id}
          contentContainerStyle={styles.content}
          refreshControl={
            <RefreshControl
              refreshing={notificationsQuery.isFetching}
              onRefresh={notificationsQuery.refetch}
              testID="notifications-refresh-control"
            />
          }
          renderItem={({ item }) => (
            <NotificationRow item={item} onPress={() => markRead.mutate(item.id)} isMarkingRead={markRead.isPending} />
          )}
        />
      )}

      {markRead.isError && (
        <ThemedView style={styles.centered}>
          <ThemedText testID="notifications-mark-read-error">{getErrorMessage(markRead.error)}</ThemedText>
        </ThemedView>
      )}
    </SafeAreaView>
  );
}

function NotificationRow({
  item,
  onPress,
  isMarkingRead,
}: {
  item: NotificationViewModel;
  onPress: () => void;
  isMarkingRead: boolean;
}) {
  return (
    <TouchableOpacity
      onPress={onPress}
      disabled={isMarkingRead}
      testID="notification-row"
      accessibilityRole="button"
      accessibilityLabel={item.isRead ? '既読のお知らせ' : '未読のお知らせ、タップして既読にする'}
    >
      <ThemedView style={[styles.card, !item.isRead && styles.cardUnread]}>
        <ThemedView style={styles.titleRow}>
          {!item.isRead && <ThemedView style={styles.unreadDot} testID="notification-unread-dot" />}
          <ThemedText type={item.isRead ? 'default' : 'smallBold'} testID="notification-title">
            {item.title}
          </ThemedText>
        </ThemedView>
        {item.summary && <ThemedText type="small">{item.summary}</ThemedText>}
        <ThemedText type="small" themeColor="textSecondary">
          {item.publishedAtDisplay}
        </ThemedText>
      </ThemedView>
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  safeArea: { flex: 1 },
  centered: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: Spacing.four, gap: Spacing.two },
  content: { flexGrow: 1, padding: Spacing.four, gap: Spacing.three },
  card: {
    borderWidth: 1,
    borderColor: '#e1dee6',
    borderRadius: 10,
    padding: Spacing.three,
    gap: Spacing.one,
  },
  cardUnread: {
    borderColor: '#3c87f7',
  },
  titleRow: { flexDirection: 'row', alignItems: 'center', gap: Spacing.one },
  unreadDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    backgroundColor: '#3c87f7',
  },
});
