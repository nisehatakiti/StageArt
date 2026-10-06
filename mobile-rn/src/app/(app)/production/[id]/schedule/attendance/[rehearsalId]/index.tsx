import { useLocalSearchParams, useRouter } from 'expo-router';
import { useMemo, useState } from 'react';
import { ActivityIndicator, FlatList, StyleSheet, TouchableOpacity } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { ScheduleCommentList } from '@/components/schedule-comment-list';
import { ThemedText } from '@/components/themed-text';
import { ThemedTextInput } from '@/components/themed-text-input';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';
import { attendanceGroup, attendanceSummary, responseOptionsForPhase, statusLabel } from '@/features/attendance/viewModel';
import type { AttendanceGroup } from '@/features/attendance/viewModel';
import { phaseForRehearsalStatus } from '@/features/attendance/phase';
import { useProduction } from '@/features/production/useProductions';
import { formatDayHeader, formatTime } from '@/features/schedule/groupByDay';
import {
  useCancelRehearsal,
  useConfirmRehearsal,
  useRehearsal,
} from '@/features/attendance/useRehearsals';
import {
  useRehearsalAttendances,
  useRespondRehearsalAttendance,
} from '@/features/attendance/useRehearsalAttendance';
import { useParticipants } from '@/features/participant/useParticipant';
import { useCurrentPerson } from '@/features/person/useCurrentPerson';
import {
  useDeleteRehearsalComment,
  usePostRehearsalComment,
  useRehearsalComments,
  useUpdateRehearsalComment,
} from '@/features/schedule/useScheduleComments';
import { confirmAlert } from '@/utils/confirmAlert';
import { getErrorMessage } from '@/utils/errorMessage';
import type { RehearsalAttendance, ScheduleComment } from '@/types/api';

/** Rehearsal detail status labels. */
const REHEARSAL_STATUS_LABELS: Record<string, string> = {
  DRAFT: '下書き',
  SCHEDULED: '調整中',
  CONFIRMED: '確定',
  ACTIVE: '実施中',
  COMPLETED: '実施済み',
  CANCELLED: '中止',
};

function rehearsalStatusLabel(status: string): string {
  return REHEARSAL_STATUS_LABELS[status] ?? status;
}

/**
 * Rehearsal status badge colors - deliberately reuses the exact 4 colors
 * already established on this screen (confirmButton/attendingButton
 * green, cancelButton/absentButton red, editButton amber,
 * unansweredButton gray) rather than inventing a new palette.
 * CONFIRMED/ACTIVE take the same green as "稽古情報を確定する";
 * CANCELLED takes the same red as "中止" (identical label, too);
 * SCHEDULED (調整中) takes the same amber as "編集" (an in-progress,
 * still-adjustable state); DRAFT/COMPLETED take the neutral gray also
 * used for 未回答 (neither is a state needing attention right now).
 */
const REHEARSAL_STATUS_COLORS: Record<string, string> = {
  DRAFT: '#777',
  SCHEDULED: '#d99a00',
  CONFIRMED: '#2e7d32',
  ACTIVE: '#2e7d32',
  COMPLETED: '#777',
  CANCELLED: '#c62828',
};

function rehearsalStatusColor(status: string): string {
  return REHEARSAL_STATUS_COLORS[status] ?? '#777';
}

const ATTENDANCE_GROUP_COLORS: Record<AttendanceGroup, string> = {
  ATTENDING: '#2e7d32',
  NOT_ATTENDING: '#c62828',
  UNANSWERED: '#777',
};

const ATTENDANCE_GROUP_LABELS: Record<AttendanceGroup, string> = {
  ATTENDING: '出席',
  NOT_ATTENDING: '欠席',
  UNANSWERED: '未回答',
};

/**
 * §17-19: Attendance roster + self-response for one Rehearsal. Whether
 * the caller may respond to a given row (own record only) or record an
 * actual result is enforced server-side; this screen does not hide the
 * "実績を記録" controls by Role (§24) - an unauthorized attempt shows the
 * Backend's own 403 message instead.
 */
export default function RehearsalAttendanceScreen() {
  const { id: productionId, rehearsalId } = useLocalSearchParams<{ id: string; rehearsalId: string }>();
  const router = useRouter();

  const rehearsalQuery = useRehearsal(rehearsalId);
  const phase = rehearsalQuery.data ? phaseForRehearsalStatus(rehearsalQuery.data.status) : undefined;
  /** Phase 7 (Rehearsal仕様整合) §4/§10: "回答期限" governs
   * SCHEDULE_ADJUSTMENT (予定) self-response specifically - Backend
   * (RespondRehearsalAttendanceUseCase) is the authority (this is UX
   * only, so the member sees why before submitting rather than only
   * after a 422). */
  const responseDeadlinePassed =
    phase === 'SCHEDULE_ADJUSTMENT' &&
    !!rehearsalQuery.data?.response_deadline &&
    new Date() > new Date(rehearsalQuery.data.response_deadline);

  const attendancesQuery = useRehearsalAttendances(rehearsalId, phase);
  const currentPersonQuery = useCurrentPerson();
  const respondMutation = useRespondRehearsalAttendance(rehearsalId, phase);

  const participantsQuery = useParticipants(productionId);
  const productionQuery = useProduction(productionId);
  const canManageRehearsal =
    productionQuery.data?.is_primary_manager === true ||
    productionQuery.data?.delegate_roles?.includes('REHEARSAL_MANAGER') === true;

  const confirmRehearsal = useConfirmRehearsal(rehearsalId);
  const cancelRehearsal = useCancelRehearsal(rehearsalId);
  const commentsQuery = useRehearsalComments(rehearsalId);
  const postComment = usePostRehearsalComment(rehearsalId as string);
  const updateComment = useUpdateRehearsalComment(rehearsalId);
  const deleteComment = useDeleteRehearsalComment(rehearsalId);
  const [commentDraft, setCommentDraft] = useState('');
  const [editingCommentId, setEditingCommentId] = useState<string | null>(null);
  const [editDraft, setEditDraft] = useState('');
  const [deleteTargetCommentId, setDeleteTargetCommentId] = useState<string | null>(null);

  async function handlePostComment() {
    const body = commentDraft.trim();
    if (!body) return;

    await postComment.mutateAsync(body);
    setCommentDraft('');
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
    confirmAlert('コメントを削除', 'このコメントを削除しますか？', [
      { text: 'キャンセル', style: 'cancel' },
      {
        text: '削除',
        style: 'destructive',
        onPress: () => deleteComment.mutate(comment.id),
      },
    ]);
  }

  const myPersonId = currentPersonQuery.data?.id;
  const myRecord = useMemo(
    () => attendancesQuery.data?.find((a) => a.person_id === myPersonId),
    [attendancesQuery.data, myPersonId]
  );
  const [remarksDraft, setRemarksDraft] = useState('');
  const [remarksInitializedForId, setRemarksInitializedForId] = useState<string | null>(null);
  if (myRecord && remarksInitializedForId !== myRecord.id) {
    setRemarksDraft(myRecord.remarks ?? '');
    setRemarksInitializedForId(myRecord.id);
  }
  const participantNameByPersonId = useMemo(() => {
    const map = new Map<string, string>();
    for (const participant of participantsQuery.data ?? []) {
      if (participant.subject_type !== 'PERSON' || participant.status !== 'ACTIVE') continue;
      const fullName = [participant.person_family_name, participant.person_given_name].filter(Boolean).join(' ');
      const name = participant.display_name?.trim() || fullName;
      if (name) map.set(participant.subject_id, name);
    }
    return map;
  }, [participantsQuery.data]);

  const commentAuthorNameByPersonId = useMemo(() => {
    const map = new Map(participantNameByPersonId);
    if (myPersonId) {
      const myName = [currentPersonQuery.data?.family_name, currentPersonQuery.data?.given_name].filter(Boolean).join(' ');
      if (myName) map.set(myPersonId, myName);
    }
    return map;
  }, [participantNameByPersonId, currentPersonQuery.data, myPersonId]);

  const summary = useMemo(
    () => attendanceSummary((attendancesQuery.data ?? []).map((a) => a.status)),
    [attendancesQuery.data]
  );

  /** 出席 -> 欠席 -> 未回答, stable within each group (Array.prototype.sort
   * is stable per the ES2019+ spec) so the existing natural order inside
   * a group is preserved - only a status-based grouping is added. */
  const sortedAttendances = useMemo(() => {
    const groupOrder: Record<AttendanceGroup, number> = { ATTENDING: 0, NOT_ATTENDING: 1, UNANSWERED: 2 };
    return [...(attendancesQuery.data ?? [])].sort(
      (a, b) => groupOrder[attendanceGroup(a.status)] - groupOrder[attendanceGroup(b.status)]
    );
  }, [attendancesQuery.data]);

  const isLoading = rehearsalQuery.isLoading || attendancesQuery.isLoading;
  const isError = rehearsalQuery.isError || attendancesQuery.isError;
  const loadError = rehearsalQuery.error ?? attendancesQuery.error;

  return (
    <SafeAreaView style={styles.safeArea}>
      <TouchableOpacity onPress={() => router.back()} testID="attendance-back" style={styles.backRow}>
        <ThemedText type="link">← 稽古一覧</ThemedText>
      </TouchableOpacity>

      {isLoading && (
        <ThemedView style={styles.centered}>
          <ActivityIndicator testID="attendance-loading" />
        </ThemedView>
      )}

      {isError && (
        <ThemedView style={styles.centered}>
          <ThemedText testID="attendance-error">{getErrorMessage(loadError)}</ThemedText>
          <TouchableOpacity
            onPress={() => {
              rehearsalQuery.refetch();
              attendancesQuery.refetch();
            }}
            testID="attendance-retry"
            accessibilityRole="button"
            accessibilityLabel="再読み込み"
          >
            <ThemedText type="link">再読み込み</ThemedText>
          </TouchableOpacity>
        </ThemedView>
      )}

      {!isLoading && !isError && phase && (
        <FlatList
          testID="attendance-roster"
          data={sortedAttendances}
          keyExtractor={(a) => a.id}
          numColumns={2}
          columnWrapperStyle={styles.rosterColumns}
          contentContainerStyle={styles.list}
          ListHeaderComponent={
            <ThemedView>
              {rehearsalQuery.data && (
                <ThemedView style={styles.summaryCard} testID="rehearsal-info">
                  <ThemedView style={styles.rehearsalInfoHeader}>
                    <ThemedView
                      style={[styles.statusBadge, { backgroundColor: rehearsalStatusColor(rehearsalQuery.data.status) }]}
                      testID="rehearsal-info-status"
                    >
                      <ThemedText style={styles.actionButtonText}>{rehearsalStatusLabel(rehearsalQuery.data.status)}</ThemedText>
                    </ThemedView>
                    {canManageRehearsal && <ThemedView style={styles.actionRow}>
                      {(rehearsalQuery.data.status === 'SCHEDULED' || rehearsalQuery.data.status === 'CONFIRMED') && (
                        <TouchableOpacity
                          testID="rehearsal-edit"
                          onPress={() => router.push(`/production/${productionId}/schedule/attendance/${rehearsalId}/edit`)}
                          style={[styles.actionButton, styles.editButton]}
                          accessibilityRole="button"
                          accessibilityLabel="稽古を編集する"
                        >
                          <ThemedText style={styles.actionButtonText}>編集</ThemedText>
                        </TouchableOpacity>
                      )}
                      {(rehearsalQuery.data.status === 'SCHEDULED' ||
                        rehearsalQuery.data.status === 'CONFIRMED' ||
                        rehearsalQuery.data.status === 'ACTIVE') && (
                        <TouchableOpacity
                          testID="rehearsal-cancel"
                          onPress={() =>
                            confirmAlert('稽古を中止する', 'この稽古を中止しますか？この操作は元に戻せません。', [
                              { text: 'キャンセル', style: 'cancel' },
                              { text: '中止する', style: 'destructive', onPress: () => cancelRehearsal.mutate() },
                            ])
                          }
                          disabled={cancelRehearsal.isPending}
                          style={[styles.actionButton, styles.cancelButton]}
                          accessibilityRole="button"
                          accessibilityLabel="稽古を中止する"
                        >
                          {cancelRehearsal.isPending ? <ActivityIndicator color="#fff" /> : <ThemedText style={styles.actionButtonText}>中止</ThemedText>}
                        </TouchableOpacity>
                      )}
                    </ThemedView>}
                  </ThemedView>
                  {rehearsalQuery.data.start_date_time && (
                    <ThemedText testID="rehearsal-info-datetime" style={styles.rehearsalDateTime}>
                      {formatDayHeader(new Date(rehearsalQuery.data.start_date_time))} {formatTime(rehearsalQuery.data.start_date_time)}
                      {rehearsalQuery.data.end_date_time ? `〜${formatTime(rehearsalQuery.data.end_date_time)}` : ''}
                    </ThemedText>
                  )}
                  {rehearsalQuery.data.location && (
                    <ThemedView>
                      <ThemedText type="smallBold">場所</ThemedText>
                      <ThemedText testID="rehearsal-info-location" style={styles.rehearsalLocation}>
                        {rehearsalQuery.data.location}
                      </ThemedText>
                    </ThemedView>
                  )}
                  <ThemedText type="smallBold">備考</ThemedText>
                  <ThemedText testID="rehearsal-info-description">
                    {rehearsalQuery.data.description?.trim() || 'なし'}
                  </ThemedText>
                  {canManageRehearsal && rehearsalQuery.data.status === 'SCHEDULED' && (
                    <TouchableOpacity
                      testID="rehearsal-confirm"
                      onPress={() => confirmRehearsal.mutate()}
                      disabled={confirmRehearsal.isPending}
                      style={[styles.actionButton, styles.confirmButton]}
                      accessibilityRole="button"
                      accessibilityLabel="稽古情報を確定する"
                    >
                      {confirmRehearsal.isPending ? <ActivityIndicator color="#fff" /> : <ThemedText style={styles.actionButtonText}>稽古情報を確定する</ThemedText>}
                    </TouchableOpacity>
                  )}
                  {canManageRehearsal && confirmRehearsal.isError && <ThemedText testID="rehearsal-confirm-error">{getErrorMessage(confirmRehearsal.error)}</ThemedText>}
                  {canManageRehearsal && cancelRehearsal.isError && <ThemedText testID="rehearsal-cancel-error">{getErrorMessage(cancelRehearsal.error)}</ThemedText>}
                </ThemedView>
              )}
              <ThemedView style={styles.compactCard} testID="attendance-summary">
                <ThemedText type="smallBold">出欠回答状況</ThemedText>
                <ThemedText testID="attendance-summary-attending" style={styles.summaryAttending}>出席 {summary.attending}名</ThemedText>
                <ThemedText testID="attendance-summary-not-attending" style={styles.summaryNotAttending}>欠席 {summary.notAttending}名</ThemedText>
                <ThemedText testID="attendance-summary-unanswered" style={styles.summaryUnanswered}>未回答 {summary.unanswered}名</ThemedText>
              </ThemedView>
              {myRecord && (
                <ThemedView style={styles.compactCard} testID="attendance-my-record">
                  <ThemedText type="smallBold">稽古の出欠を登録してください。</ThemedText>
                  {responseDeadlinePassed && (
                    <ThemedText testID="attendance-response-deadline-passed" themeColor="textSecondary" type="small">
                      回答期限を過ぎたため、回答・変更はできません。
                    </ThemedText>
                  )}
                  <ThemedView style={styles.responseButtonRow}>
                    {responseOptionsForPhase(phase).map((option) => {
                      const isAttending = option === 'ATTENDING' || option === 'AVAILABLE';
                      const isNotAttending = option === 'NOT_ATTENDING' || option === 'UNAVAILABLE';
                      return (
                        <TouchableOpacity
                          key={option}
                          testID={`attendance-respond-${option}`}
                          onPress={() =>
                            respondMutation.mutate({
                              attendanceId: myRecord.id,
                              status: option,
                              remarks: remarksDraft.trim() || null,
                            })
                          }
                          disabled={respondMutation.isPending || responseDeadlinePassed}
                          style={[
                            styles.responseButton,
                            isAttending ? styles.attendingButton : isNotAttending ? styles.absentButton : styles.unansweredButton,
                            (respondMutation.isPending || responseDeadlinePassed) && styles.disabledButton,
                          ]}
                        >
                          <ThemedText style={styles.actionButtonText}>{statusLabel(option)}</ThemedText>
                        </TouchableOpacity>
                      );
                    })}
                  </ThemedView>
                  <ThemedTextInput
                    testID="attendance-remarks-input"
                    placeholder="備考（任意）"
                    value={remarksDraft}
                    onChangeText={setRemarksDraft}
                    multiline
                    editable={!responseDeadlinePassed}
                    style={styles.remarksInput}
                  />
                  {respondMutation.isError && (
                    <ThemedText testID="attendance-respond-error">{getErrorMessage(respondMutation.error)}</ThemedText>
                  )}
                </ThemedView>
              )}
            </ThemedView>
          }
          renderItem={({ item }) => (
            <AttendanceRow
              attendance={item}
              displayName={participantNameByPersonId.get(item.person_id) ?? '氏名未設定'}
            />
          )}
          ListFooterComponent={
            <ThemedView style={styles.commentsSection}>
              <ThemedText type="smallBold">稽古連絡用コメント</ThemedText>
              <ScheduleCommentList
                isLoading={commentsQuery.isLoading}
                isError={commentsQuery.isError}
                error={commentsQuery.error}
                comments={commentsQuery.data}
                authorNameByPersonId={commentAuthorNameByPersonId}
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
                  testID="rehearsal-comment-input"
                  placeholder="コメントを書く"
                  value={commentDraft}
                  onChangeText={setCommentDraft}
                  style={styles.commentInput}
                  multiline
                  accessibilityLabel="コメントを書く"
                />
                <TouchableOpacity
                  testID="rehearsal-comment-submit"
                  onPress={handlePostComment}
                  disabled={!commentDraft.trim() || postComment.isPending}
                  accessibilityRole="button"
                  accessibilityLabel="コメントを投稿する"
                  style={[styles.postButton, (!commentDraft.trim() || postComment.isPending) && styles.disabledButton]}
                >
                  {postComment.isPending ? <ActivityIndicator color="#fff" /> : <ThemedText style={styles.actionButtonText}>投稿する</ThemedText>}
                </TouchableOpacity>
                {postComment.isError && (
                  <ThemedText testID="rehearsal-comment-post-error">{getErrorMessage(postComment.error)}</ThemedText>
                )}
              </ThemedView>
            </ThemedView>
          }
        />
      )}
    </SafeAreaView>
  );
}

function AttendanceRow({
  attendance,
  displayName,
}: {
  attendance: RehearsalAttendance;
  displayName: string;
}) {
  const group = attendanceGroup(attendance.status);
  return (
    <ThemedView style={styles.rosterRow} testID="attendance-roster-row">
      <ThemedText type="smallBold" numberOfLines={1}>{displayName}</ThemedText>
      <ThemedView style={[styles.statusBadge, styles.rosterStatusBadge, { backgroundColor: ATTENDANCE_GROUP_COLORS[group] }]}>
        <ThemedText style={styles.actionButtonText}>{ATTENDANCE_GROUP_LABELS[group]}</ThemedText>
      </ThemedView>
      {attendance.remarks?.trim() ? (
        <ThemedText type="small" themeColor="textSecondary" numberOfLines={2} style={styles.rosterRemarks}>
          {attendance.remarks}
        </ThemedText>
      ) : null}
    </ThemedView>
  );
}

const styles = StyleSheet.create({
  safeArea: { flex: 1 },
  backRow: { paddingHorizontal: Spacing.four, paddingVertical: Spacing.two },
  centered: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: Spacing.four, gap: Spacing.two },
  list: { padding: Spacing.four, gap: Spacing.two },
  summaryCard: {
    borderWidth: 1,
    borderColor: '#e1dee6',
    borderRadius: 10,
    padding: Spacing.three,
    gap: Spacing.one,
    marginBottom: Spacing.three,
  },
  /** 出欠回答状況/自分の回答カード向けの、より控えめなpadding/gapバージョン
   * - 稽古の日時・場所・備考（summaryCard）を主役にし、出欠部分を相対的に
   * コンパクトにするための専用スタイル。 */
  compactCard: {
    borderWidth: 1,
    borderColor: '#e1dee6',
    borderRadius: 10,
    padding: Spacing.two,
    gap: Spacing.one,
    marginBottom: Spacing.two,
  },
  rosterColumns: {
    gap: Spacing.three,
    marginBottom: Spacing.one,
    justifyContent: 'flex-start',
  },
  rosterRow: {
    width: 160,
    flexGrow: 0,
    flexShrink: 0,
    paddingVertical: Spacing.one,
    paddingHorizontal: Spacing.one,
    gap: Spacing.half,
    alignItems: 'flex-start',
  },
  rehearsalInfoHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: Spacing.two,
  },
  actionRow: { flexDirection: 'row', gap: Spacing.two, flexWrap: 'wrap' },
  actionButton: {
    minHeight: 42,
    paddingHorizontal: Spacing.three,
    paddingVertical: Spacing.two,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
  },
  actionButtonText: { color: '#fff', fontWeight: '700' },
  editButton: { backgroundColor: '#d99a00' },
  cancelButton: { backgroundColor: '#c62828' },
  confirmButton: { backgroundColor: '#2e7d32' },
  rehearsalDateTime: { fontSize: 20, lineHeight: 26, fontWeight: '700' },
  rehearsalLocation: { fontSize: 16, lineHeight: 22, fontWeight: '600' },
  summaryAttending: { color: '#2e7d32' },
  summaryNotAttending: { color: '#c62828' },
  summaryUnanswered: { color: '#777' },
  rosterStatusBadge: {
    minHeight: 28,
    paddingHorizontal: Spacing.two,
    paddingVertical: Spacing.half,
    alignSelf: 'flex-start',
  },
  rosterRemarks: { alignSelf: 'stretch' },
  remarksInput: {
    minHeight: 88,
    textAlignVertical: 'top',
    borderWidth: 1,
    borderColor: '#b7b2c0',
    borderRadius: 8,
    paddingHorizontal: Spacing.three,
    paddingVertical: Spacing.two,
    backgroundColor: '#fff',
  },
  responseButtonRow: { flexDirection: 'row', gap: Spacing.two, flexWrap: 'wrap' },
  responseButton: {
    flex: 1,
    minWidth: 120,
    minHeight: 48,
    paddingHorizontal: Spacing.three,
    paddingVertical: Spacing.two,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
  },
  statusBadge: {
    minHeight: 40,
    paddingHorizontal: Spacing.three,
    paddingVertical: Spacing.two,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
    alignSelf: 'flex-start',
  },
  attendingButton: { backgroundColor: '#2e7d32' },
  absentButton: { backgroundColor: '#c62828' },
  unansweredButton: { backgroundColor: '#777' },
  disabledButton: { opacity: 0.5 },
  commentsSection: { marginTop: Spacing.four, gap: Spacing.three },
  commentForm: { gap: Spacing.two },
  postButton: {
    minHeight: 44,
    paddingHorizontal: Spacing.four,
    paddingVertical: Spacing.two,
    borderRadius: 8,
    backgroundColor: '#1976d2',
    alignItems: 'center',
    justifyContent: 'center',
  },
  commentInput: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    paddingHorizontal: Spacing.three,
    paddingVertical: Spacing.two,
    minHeight: 44,
  },
  memberRow: { flexDirection: 'row', alignItems: 'center', gap: Spacing.two, paddingVertical: Spacing.one },
  checkbox: {
    width: 22,
    height: 22,
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 4,
    alignItems: 'center',
    justifyContent: 'center',
  },
  checkboxChecked: { backgroundColor: '#4a3f7a', borderColor: '#4a3f7a' },
  checkboxMark: { color: '#fff', fontSize: 14, lineHeight: 16 },
});
