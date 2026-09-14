import { ActivityIndicator, FlatList, RefreshControl, StyleSheet, TouchableOpacity } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';
import { useMarkMyNotificationRead, useMyNotifications } from '@/features/notifications/useNotifications';
import { getErrorMessage } from '@/utils/errorMessage';
import type { MyNotification } from '@/types/api';

/**
 * Notification基盤実装 phase §3/§15: "お知らせ" - the caller's own
 * personal Notification feed (In-App delivery, the highest-priority
 * channel this phase's instruction asks for), backed by
 * GET /me/notifications. Mirrors production/[id]/notifications.tsx's
 * card/unread-dot/tap-to-mark-read pattern, since this is the same
 * "お知らせ" concept, just personally- rather than Production-scoped.
 */
export default function MyNotificationsScreen() {
  const notificationsQuery = useMyNotifications();
  const markRead = useMarkMyNotificationRead();
  const items = notificationsQuery.data ?? [];

  return (
    <SafeAreaView style={styles.safeArea}>
      <ThemedText type="title" style={styles.title}>
        お知らせ
      </ThemedText>

      {notificationsQuery.isLoading && (
        <ThemedView style={styles.centered}>
          <ActivityIndicator testID="my-notifications-loading" />
        </ThemedView>
      )}

      {notificationsQuery.isError && (
        <ThemedView style={styles.centered}>
          <ThemedText testID="my-notifications-error">{getErrorMessage(notificationsQuery.error)}</ThemedText>
          <TouchableOpacity
            onPress={() => notificationsQuery.refetch()}
            testID="my-notifications-retry"
            accessibilityRole="button"
            accessibilityLabel="再読み込み"
          >
            <ThemedText type="link">再読み込み</ThemedText>
          </TouchableOpacity>
        </ThemedView>
      )}

      {!notificationsQuery.isLoading && !notificationsQuery.isError && items.length === 0 && (
        <ThemedView style={styles.centered}>
          <ThemedText testID="my-notifications-empty" themeColor="textSecondary">
            お知らせはまだありません。
          </ThemedText>
        </ThemedView>
      )}

      {!notificationsQuery.isLoading && !notificationsQuery.isError && items.length > 0 && (
        <FlatList
          testID="my-notifications-list"
          data={items}
          keyExtractor={(item) => item.id}
          contentContainerStyle={styles.content}
          refreshControl={
            <RefreshControl refreshing={notificationsQuery.isFetching} onRefresh={notificationsQuery.refetch} testID="my-notifications-refresh-control" />
          }
          renderItem={({ item }) => (
            <MyNotificationRow item={item} onPress={() => markRead.mutate(item.id)} isMarkingRead={markRead.isPending} />
          )}
        />
      )}

      {markRead.isError && (
        <ThemedView style={styles.centered}>
          <ThemedText testID="my-notifications-mark-read-error">{getErrorMessage(markRead.error)}</ThemedText>
        </ThemedView>
      )}
    </SafeAreaView>
  );
}

function MyNotificationRow({ item, onPress, isMarkingRead }: { item: MyNotification; onPress: () => void; isMarkingRead: boolean }) {
  return (
    <TouchableOpacity
      onPress={onPress}
      disabled={isMarkingRead}
      testID={`my-notification-row-${item.id}`}
      accessibilityRole="button"
      accessibilityLabel={item.is_read ? '既読のお知らせ' : '未読のお知らせ、タップして既読にする'}
    >
      <ThemedView style={[styles.card, !item.is_read && styles.cardUnread]}>
        <ThemedView style={styles.titleRow}>
          {!item.is_read && <ThemedView style={styles.unreadDot} testID="my-notification-unread-dot" />}
          <ThemedText type={item.is_read ? 'default' : 'smallBold'} testID="my-notification-message">
            {item.message}
          </ThemedText>
        </ThemedView>
        <ThemedText type="small" themeColor="textSecondary">
          {formatCreatedAt(item.created_at)}
        </ThemedText>
      </ThemedView>
    </TouchableOpacity>
  );
}

function formatCreatedAt(iso: string): string {
  const d = new Date(iso);

  if (Number.isNaN(d.getTime())) {
    return iso;
  }

  const date = `${d.getFullYear()}/${d.getMonth() + 1}/${d.getDate()}`;
  const time = `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;

  return `${date} ${time}`;
}

const styles = StyleSheet.create({
  safeArea: { flex: 1 },
  title: { fontSize: 22, lineHeight: 28, margin: Spacing.four, marginBottom: Spacing.two },
  centered: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: Spacing.four, gap: Spacing.two },
  content: { flexGrow: 1, padding: Spacing.four, paddingTop: 0, gap: Spacing.three },
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
