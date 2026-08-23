import { useLocalSearchParams, useRouter } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, Alert, ScrollView, StyleSheet, TouchableOpacity } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { ScheduleCommentList } from '@/components/schedule-comment-list';
import { ThemedText } from '@/components/themed-text';
import { ThemedTextInput } from '@/components/themed-text-input';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';
import { formatDayHeader, formatTime } from '@/features/schedule/groupByDay';
import {
  useDeleteTimetableItemComment,
  usePostScheduleComment,
  useTimetableItemComments,
  useUpdateTimetableItemComment,
} from '@/features/schedule/useScheduleComments';
import { useTimetableItem } from '@/features/schedule/useProductionSchedule';
import { getErrorMessage } from '@/utils/errorMessage';
import type { ScheduleComment } from '@/types/api';

const STAGE_VENUE = '舞台';

/**
 * §20 Schedule Item Detail. Timetable Version番号・Published日時は、
 * Production Schedule Read Model(GET /productions/{id}/timetable-items)
 * がtimetable_idのみを返しrehearsal_idを含まないため、rehearsal単位の
 * 追加GET(N+1)なしには取得できない(§31のN+1禁止を優先し、意図的に
 * 「確定版のみ表示」の事実表示に留めた - Phase 5.3報告のOpen Item参照)。
 */
export default function ScheduleItemDetailScreen() {
  const { itemId } = useLocalSearchParams<{ id: string; itemId: string }>();
  const router = useRouter();
  const itemQuery = useTimetableItem(itemId);
  const commentsQuery = useTimetableItemComments(itemId);
  const postComment = usePostScheduleComment(itemId as string);
  const updateComment = useUpdateTimetableItemComment(itemId);
  const deleteComment = useDeleteTimetableItemComment(itemId);
  const [draft, setDraft] = useState('');
  const [editingCommentId, setEditingCommentId] = useState<string | null>(null);
  const [editDraft, setEditDraft] = useState('');
  const [deleteTargetCommentId, setDeleteTargetCommentId] = useState<string | null>(null);

  async function handlePost() {
    const body = draft.trim();
    if (!body) return;

    await postComment.mutateAsync(body);
    setDraft('');
  }

  function handleStartEdit(comment: ScheduleComment) {
    setEditingCommentId(comment.id);
    setEditDraft(comment.body);
  }

  function handleCancelEdit() {
    setEditingCommentId(null);
    setEditDraft('');
  }

  async function handleSaveEdit() {
    const body = editDraft.trim();
    if (!editingCommentId || !body) return;

    await updateComment.mutateAsync({ commentId: editingCommentId, body });
    setEditingCommentId(null);
    setEditDraft('');
  }

  function handleDelete(comment: ScheduleComment) {
    setDeleteTargetCommentId(comment.id);
    Alert.alert('コメントを削除', 'このコメントを削除しますか？', [
      { text: 'キャンセル', style: 'cancel' },
      {
        text: '削除',
        style: 'destructive',
        onPress: () => deleteComment.mutate(comment.id),
      },
    ]);
  }

  const backButton = (
    <TouchableOpacity
      onPress={() => router.back()}
      testID="schedule-detail-back"
      accessibilityRole="button"
      accessibilityLabel="予定一覧へ戻る"
      style={styles.backRow}
    >
      <ThemedText type="link">← 予定一覧</ThemedText>
    </TouchableOpacity>
  );

  if (itemQuery.isLoading) {
    return (
      <SafeAreaView style={styles.safeArea}>
        {backButton}
        <ThemedView style={styles.centered}>
          <ActivityIndicator />
        </ThemedView>
      </SafeAreaView>
    );
  }

  if (itemQuery.isError || !itemQuery.data) {
    return (
      <SafeAreaView style={styles.safeArea}>
        {backButton}
        <ThemedView style={styles.centered}>
          <ThemedText testID="detail-error">{getErrorMessage(itemQuery.error)}</ThemedText>
        </ThemedView>
      </SafeAreaView>
    );
  }

  const item = itemQuery.data;
  const isStageUsage = item.venue === STAGE_VENUE;

  return (
    <SafeAreaView style={styles.safeArea}>
      {backButton}
      <ScrollView contentContainerStyle={styles.content}>
        <ThemedText type="title" style={styles.itemTitle}>
          {item.title}
        </ThemedText>

        <ThemedView style={styles.field}>
          <ThemedText type="smallBold">日付</ThemedText>
          <ThemedText>{formatDayHeader(new Date(item.start_date_time))}</ThemedText>
        </ThemedView>

        <ThemedView style={styles.field}>
          <ThemedText type="smallBold">時刻</ThemedText>
          <ThemedText>
            {formatTime(item.start_date_time)}
            {item.end_date_time ? ` - ${formatTime(item.end_date_time)}` : '（終了時刻未設定）'}
          </ThemedText>
        </ThemedView>

        {item.category && (
          <ThemedView style={styles.field}>
            <ThemedText type="smallBold">Category</ThemedText>
            <ThemedText testID="detail-category">{item.category}</ThemedText>
          </ThemedView>
        )}

        {item.venue && (
          <ThemedView style={styles.field}>
            <ThemedText type="smallBold">場所</ThemedText>
            <ThemedText testID="detail-venue">
              {item.venue}
              {isStageUsage ? '（舞台使用）' : ''}
            </ThemedText>
          </ThemedView>
        )}

        {item.participant_type && (
          <ThemedView style={styles.field}>
            <ThemedText type="smallBold">対象Role</ThemedText>
            <ThemedText testID="detail-participant-type">{item.participant_type}</ThemedText>
          </ThemedView>
        )}

        {item.target_person_ids.length > 0 && (
          <ThemedView style={styles.field}>
            <ThemedText type="smallBold">対象Person（{item.target_person_ids.length}名）</ThemedText>
            <ThemedText type="small" themeColor="textSecondary" testID="detail-target-persons">
              {item.target_person_ids.join(', ')}
            </ThemedText>
          </ThemedView>
        )}

        {item.description && (
          <ThemedView style={styles.field}>
            <ThemedText type="smallBold">Description</ThemedText>
            <ThemedText>{item.description}</ThemedText>
          </ThemedView>
        )}

        {item.notes && (
          <ThemedView style={styles.field}>
            <ThemedText type="smallBold">Notes</ThemedText>
            <ThemedText>{item.notes}</ThemedText>
          </ThemedView>
        )}

        <ThemedView style={styles.field}>
          <ThemedText type="small" themeColor="textSecondary">
            確定版（Published）の内容を表示しています。
          </ThemedText>
        </ThemedView>

        <ThemedView style={styles.commentsSection}>
          <ThemedText type="subtitle">コメント</ThemedText>
          <ScheduleCommentList
            isLoading={commentsQuery.isLoading}
            isError={commentsQuery.isError}
            error={commentsQuery.error}
            comments={commentsQuery.data}
            onEdit={handleStartEdit}
            onDelete={handleDelete}
            editingCommentId={editingCommentId}
            editDraft={editDraft}
            onEditDraftChange={setEditDraft}
            onSaveEdit={handleSaveEdit}
            onCancelEdit={handleCancelEdit}
            isSavingEdit={updateComment.isPending}
            editError={updateComment.isError && updateComment.variables?.commentId === editingCommentId ? updateComment.error : null}
            deleteTargetCommentId={deleteTargetCommentId}
            isDeletePending={deleteComment.isPending}
            deleteError={deleteComment.isError ? deleteComment.error : null}
          />

          <ThemedView style={styles.commentForm}>
            <ThemedTextInput
              testID="comment-input"
              placeholder="コメントを書く"
              value={draft}
              onChangeText={setDraft}
              style={styles.commentInput}
              multiline
              accessibilityLabel="コメントを書く"
            />
            <TouchableOpacity
              testID="comment-submit"
              onPress={handlePost}
              disabled={!draft.trim() || postComment.isPending}
              style={[styles.commentButton, (!draft.trim() || postComment.isPending) && styles.commentButtonDisabled]}
              accessibilityRole="button"
              accessibilityLabel="コメントを投稿する"
            >
              {postComment.isPending ? (
                <ActivityIndicator color="#fff" />
              ) : (
                <ThemedText style={styles.commentButtonText}>投稿する</ThemedText>
              )}
            </TouchableOpacity>
            {postComment.isError && (
              <ThemedText testID="comment-post-error">{getErrorMessage(postComment.error)}</ThemedText>
            )}
          </ThemedView>
        </ThemedView>
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safeArea: { flex: 1 },
  backRow: { paddingHorizontal: Spacing.four, paddingVertical: Spacing.two },
  centered: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: Spacing.four },
  content: { padding: Spacing.four, gap: Spacing.three },
  itemTitle: { fontSize: 24, lineHeight: 30 },
  field: { gap: 2 },
  commentsSection: { marginTop: Spacing.four, gap: Spacing.three },
  commentForm: { gap: Spacing.two },
  commentInput: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    paddingHorizontal: Spacing.three,
    paddingVertical: Spacing.two,
    minHeight: 44,
  },
  commentButton: {
    backgroundColor: '#4a3f7a',
    borderRadius: 8,
    paddingVertical: Spacing.two,
    alignItems: 'center',
  },
  commentButtonDisabled: { opacity: 0.5 },
  commentButtonText: { color: '#fff', fontWeight: '600' },
});
