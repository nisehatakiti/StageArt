import { ActivityIndicator, StyleSheet, TouchableOpacity } from 'react-native';

import { Spacing } from '@/constants/theme';
import type { ScheduleComment } from '@/types/api';
import { getErrorMessage } from '@/utils/errorMessage';

import { ThemedText } from './themed-text';
import { ThemedTextInput } from './themed-text-input';
import { ThemedView } from './themed-view';

/**
 * §23: comment list -> body -> author -> posted-at, nothing more
 * elaborate. §22: no Role-based filtering - every comment fetched is
 * shown, in order, to every viewer.
 *
 * `author_person_id` is shown as-is: the Backend's Person Domain
 * (Person.php) exposes no display name field at all today (confirmed by
 * reading Person.php / CurrentPersonResult.php - there is no
 * `GET /people/{id}` or name field anywhere), so resolving it to a
 * human-readable name is not possible without a new Backend API, which
 * is out of scope this Phase (see the Phase 5.3 report's Open Items).
 *
 * Phase 6.4: `onEdit`/`onDelete` are optional - omitting them (as the
 * TimetableItem comment screen still does) renders exactly as before.
 * Edit/Delete controls are shown on every comment regardless of author,
 * per this Phase's explicit instruction not to hide them based on a
 * client-guessed Authorization rule (only the author may actually save
 * an edit; only the author or a Rehearsal manager may delete) - the
 * Backend's own 403 is what actually enforces this, surfaced via
 * `editError`/`deleteError` below.
 */
export function ScheduleCommentList({
  isLoading,
  isError,
  error,
  comments,
  onEdit,
  onDelete,
  editingCommentId,
  editDraft,
  onEditDraftChange,
  onSaveEdit,
  onCancelEdit,
  isSavingEdit,
  editError,
  deleteTargetCommentId,
  isDeletePending,
  deleteError,
}: {
  isLoading: boolean;
  isError: boolean;
  error: unknown;
  comments: ScheduleComment[] | undefined;
  onEdit?: (comment: ScheduleComment) => void;
  onDelete?: (comment: ScheduleComment) => void;
  editingCommentId?: string | null;
  editDraft?: string;
  onEditDraftChange?: (text: string) => void;
  onSaveEdit?: () => void;
  onCancelEdit?: () => void;
  isSavingEdit?: boolean;
  editError?: unknown;
  /** The comment ID the most recent delete attempt targeted - used for
   * both the pending "削除中…" label and, if that attempt failed, for
   * showing `deleteError` under the correct row (it stays set after a
   * failure so the error can be attributed; the caller clears it again
   * on the next successful delete or a fresh attempt). */
  deleteTargetCommentId?: string | null;
  isDeletePending?: boolean;
  deleteError?: unknown;
}) {
  if (isLoading) {
    return (
      <ThemedView style={styles.centered}>
        <ActivityIndicator />
      </ThemedView>
    );
  }

  if (isError) {
    return (
      <ThemedView style={styles.centered}>
        <ThemedText testID="comments-error">{getErrorMessage(error)}</ThemedText>
      </ThemedView>
    );
  }

  if (!comments || comments.length === 0) {
    return (
      <ThemedView style={styles.centered}>
        <ThemedText testID="comments-empty">まだコメントはありません。</ThemedText>
      </ThemedView>
    );
  }

  return (
    <ThemedView testID="comment-list" style={styles.list}>
      {comments.map((comment) => {
        const isEditing = editingCommentId === comment.id;
        const isDeleteTarget = deleteTargetCommentId === comment.id;

        return (
          <ThemedView key={comment.id} style={styles.comment} testID={`comment-${comment.id}`}>
            {isEditing ? (
              <ThemedView style={styles.editForm}>
                <ThemedTextInput
                  testID={`comment-edit-input-${comment.id}`}
                  value={editDraft}
                  onChangeText={onEditDraftChange}
                  style={styles.editInput}
                  multiline
                  accessibilityLabel="コメントを編集"
                />
                <ThemedView style={styles.actionRow}>
                  <TouchableOpacity
                    testID={`comment-save-${comment.id}`}
                    onPress={onSaveEdit}
                    disabled={!editDraft?.trim() || isSavingEdit}
                    accessibilityRole="button"
                    accessibilityLabel="保存する"
                  >
                    <ThemedText type="link">{isSavingEdit ? '保存中…' : '保存する'}</ThemedText>
                  </TouchableOpacity>
                  <TouchableOpacity
                    testID={`comment-cancel-edit-${comment.id}`}
                    onPress={onCancelEdit}
                    accessibilityRole="button"
                    accessibilityLabel="編集をキャンセル"
                  >
                    <ThemedText type="link">キャンセル</ThemedText>
                  </TouchableOpacity>
                </ThemedView>
                {editError !== null && editError !== undefined && (
                  <ThemedText testID={`comment-edit-error-${comment.id}`}>{getErrorMessage(editError)}</ThemedText>
                )}
              </ThemedView>
            ) : (
              <>
                <ThemedText type="default">{comment.body}</ThemedText>
                <ThemedText type="small" themeColor="textSecondary">
                  {comment.author_person_id} ・ {formatDateTime(comment.created_at)}
                </ThemedText>
              </>
            )}

            {!isEditing && (onEdit || onDelete) && (
              <ThemedView style={styles.actionRow}>
                {onEdit && (
                  <TouchableOpacity
                    testID={`comment-edit-${comment.id}`}
                    onPress={() => onEdit(comment)}
                    accessibilityRole="button"
                    accessibilityLabel="コメントを編集する"
                  >
                    <ThemedText type="link">編集</ThemedText>
                  </TouchableOpacity>
                )}
                {onDelete && (
                  <TouchableOpacity
                    testID={`comment-delete-${comment.id}`}
                    onPress={() => onDelete(comment)}
                    disabled={isDeleteTarget && isDeletePending}
                    accessibilityRole="button"
                    accessibilityLabel="コメントを削除する"
                  >
                    <ThemedText type="link">{isDeleteTarget && isDeletePending ? '削除中…' : '削除'}</ThemedText>
                  </TouchableOpacity>
                )}
              </ThemedView>
            )}
            {isDeleteTarget && deleteError !== null && deleteError !== undefined && (
              <ThemedText testID={`comment-delete-error-${comment.id}`}>{getErrorMessage(deleteError)}</ThemedText>
            )}
          </ThemedView>
        );
      })}
    </ThemedView>
  );
}

function formatDateTime(iso: string): string {
  const d = new Date(iso);
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}/${pad(d.getMonth() + 1)}/${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

const styles = StyleSheet.create({
  centered: { alignItems: 'center', justifyContent: 'center', padding: Spacing.four },
  list: { gap: Spacing.two },
  comment: {
    padding: Spacing.two,
    borderWidth: 1,
    borderColor: '#e1dee6',
    borderRadius: 8,
    gap: 2,
  },
  actionRow: { flexDirection: 'row', gap: Spacing.three, marginTop: Spacing.one },
  editForm: { gap: Spacing.two },
  editInput: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    paddingHorizontal: Spacing.two,
    paddingVertical: Spacing.one,
    minHeight: 44,
  },
});
