import { useLocalSearchParams, useRouter } from 'expo-router';
import { useMemo, useState } from 'react';
import { ActivityIndicator, FlatList, StyleSheet, TouchableOpacity, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { ScheduleCommentList } from '@/components/schedule-comment-list';
import { ThemedText } from '@/components/themed-text';
import { ThemedTextInput } from '@/components/themed-text-input';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';
import {
  ACTUAL_STATUS_OPTIONS,
  attendanceGroup,
  attendanceSummary,
  responseOptionsForPhase,
  statusLabel,
} from '@/features/attendance/viewModel';
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
  useAddRehearsalAttendanceTargets,
  useRecordActualRehearsalAttendanceStatus,
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
import type { Participant, RehearsalAttendance, ScheduleComment } from '@/types/api';
import { rehearsalActivityTypeLabel } from '@/features/attendance/activityType';
import { isOverdueScheduled, OVERDUE_SCHEDULED_MESSAGE } from '@/features/attendance/overdue';

/** Rehearsal detail status labels. */
const REHEARSAL_STATUS_LABELS: Record<string, string> = {
  DRAFT: '下書き',
  SCHEDULED: '調整中',
  CONFIRMED: '確定',
  ACTIVE: '実施中',
  COMPLETED: '実施済み',
  CANCELLED: '中止',
};

const PARTICIPANT_TYPE_LABEL: Record<string, string> = { CAST: '出演者', STAFF: 'スタッフ' };

/** Rehearsal statuses in which the Backend refuses each operation - kept
 * identical to the server-side guards so the screen only offers what the
 * API will accept (the API remains the authority):
 * - adding targets: AddRehearsalAttendanceTargetsUseCase::TARGET_ADDITION_BLOCKED_STATUSES
 * - self-response: RespondRehearsalAttendanceUseCase
 * - actual result: RecordActualRehearsalAttendanceStatusUseCase (ACTIVE/COMPLETED only) */
const TARGET_ADDITION_BLOCKED_STATUSES = new Set(['ACTIVE', 'COMPLETED', 'CANCELLED']);
const RESPONSE_BLOCKED_STATUSES = new Set(['ACTIVE', 'COMPLETED', 'CANCELLED']);
const ACTUAL_RECORDING_STATUSES = new Set(['ACTIVE', 'COMPLETED']);
const ACTUAL_STATUS_LABELS: Record<string, string> = {
  ATTENDED: '出席',
  LATE: '遅刻',
  EARLY_LEFT: '早退',
  ABSENT: '欠席',
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
 * §17-19: Attendance roster + self-response for one Rehearsal.
 *
 * PR #7 R8 (docs/20-RehearsalManagementScreen.md §6.2, §12, §13):
 * - managers add Production members who are not targets yet (the
 *   Backend notifies only the newly added members);
 * - the member's own answer is shown; answer buttons appear while
 *   unanswered, and (R9, §8.1) an answered member can open
 *   「回答を変更する」 until the deadline / the start of the Rehearsal;
 * - there are no manual start/finish buttons: the Backend moves a
 *   CONFIRMED Rehearsal to ACTIVE at its start time and to COMPLETED at
 *   its end time whenever it is read or written (RehearsalStatusTimeSync);
 * - while ACTIVE/COMPLETED, managers choose each member's result from a
 *   pull-down and save with 「更新」 (no notification to the member).
 *   R9: every member has a pull-down whatever they answered; the badge
 *   keeps showing the answer and the pull-down the recorded result
 *   (`actual_status`), so the two are never mixed.
 * Authorization stays server-side; the manager-only controls are hidden
 * from non-managers only to avoid offering an action that would be 403.
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

  const addTargetsMutation = useAddRehearsalAttendanceTargets(rehearsalId, phase);
  const recordActualMutation = useRecordActualRehearsalAttendanceStatus(rehearsalId, phase);
  const [selectedNewMemberIds, setSelectedNewMemberIds] = useState<Set<string>>(new Set());
  /** attendanceId -> chosen actual status, saved together by 「更新」. */
  const [pendingActual, setPendingActual] = useState<Record<string, string>>({});
  const [openActualSelectId, setOpenActualSelectId] = useState<string | null>(null);
  const [isSavingActual, setIsSavingActual] = useState(false);
  const [actualSaveError, setActualSaveError] = useState<unknown>(null);

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
  /** R9: an answered member opened 「回答を変更する」. */
  const [isChangingAnswer, setIsChangingAnswer] = useState(false);
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

  const rehearsalStatus = rehearsalQuery.data?.status;
  const canAddTargets = canManageRehearsal && !!rehearsalStatus && !TARGET_ADDITION_BLOCKED_STATUSES.has(rehearsalStatus);
  const canRecordActual = canManageRehearsal && !!rehearsalStatus && ACTUAL_RECORDING_STATUSES.has(rehearsalStatus);
  /** The member's own answer can be given or changed while the Rehearsal
   * accepts answers (§8.1); past the 調整中 deadline it is shown but
   * locked (§8.2). The Backend enforces both. */
  const responseOpen = !!myRecord && !!rehearsalStatus && !RESPONSE_BLOCKED_STATUSES.has(rehearsalStatus);
  const isUnanswered = myRecord?.status === 'UNANSWERED';
  const canChangeAnswer = responseOpen && !isUnanswered && !responseDeadlinePassed;
  const showResponseForm = responseOpen && (isUnanswered || (isChangingAnswer && canChangeAnswer));

  /** Production members (ACTIVE, Person-subject) who are not targets of
   * this Rehearsal yet. The Backend re-validates the same pool and skips
   * anyone already present, so this list is display-only. */
  const unselectedMembers = useMemo(() => {
    const currentTargetPersonIds = new Set((attendancesQuery.data ?? []).map((a) => a.person_id));
    return (participantsQuery.data ?? []).filter(
      (participant): participant is Participant =>
        participant.subject_type === 'PERSON' && participant.status === 'ACTIVE' && !currentTargetPersonIds.has(participant.subject_id)
    );
  }, [participantsQuery.data, attendancesQuery.data]);

  function toggleNewMember(personId: string) {
    setSelectedNewMemberIds((current) => {
      const next = new Set(current);
      if (next.has(personId)) {
        next.delete(personId);
      } else {
        next.add(personId);
      }
      return next;
    });
  }

  function handleAddSelectedMembers() {
    if (selectedNewMemberIds.size === 0) return;

    addTargetsMutation.mutate(Array.from(selectedNewMemberIds), {
      onSuccess: () => setSelectedNewMemberIds(new Set()),
    });
  }

  function handleSelectActual(attendanceId: string, status: string) {
    setPendingActual((current) => ({ ...current, [attendanceId]: status }));
    setOpenActualSelectId(null);
  }

  /** Saves every changed row; rows that fail stay pending so 「更新」 can be
   * pressed again. One request per row (the existing endpoint is per
   * record). */
  async function handleSaveActual() {
    const changes = Object.entries(pendingActual);
    if (changes.length === 0) return;

    setIsSavingActual(true);
    setActualSaveError(null);
    try {
      for (const [attendanceId, status] of changes) {
        await recordActualMutation.mutateAsync({ attendanceId, status });
        setPendingActual((current) => {
          const next = { ...current };
          delete next[attendanceId];
          return next;
        });
      }
    } catch (error) {
      setActualSaveError(error);
    } finally {
      setIsSavingActual(false);
    }
  }

  const pendingActualCount = Object.keys(pendingActual).length;
  const actualRowState = useMemo(
    () => ({ pendingActual, openActualSelectId, canRecordActual }),
    [pendingActual, openActualSelectId, canRecordActual]
  );

  const isLoading = rehearsalQuery.isLoading || attendancesQuery.isLoading;
  const isError = rehearsalQuery.isError || attendancesQuery.isError;
  const loadError = rehearsalQuery.error ?? attendancesQuery.error;

  return (
    <SafeAreaView style={styles.safeArea}>
      <TouchableOpacity onPress={() => router.back()} testID="attendance-back" style={styles.backRow}>
        <ThemedText type="link">← 予定</ThemedText>
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
          // Rows depend on the pending pull-down choices, not only on `data`.
          extraData={actualRowState}
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
                          accessibilityLabel="予定を編集する"
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
                  <ThemedView style={styles.rehearsalSecondaryInfo}>
                    <ThemedText type="smallBold">種別</ThemedText>
                    <ThemedText testID="rehearsal-info-activity-type" style={styles.rehearsalLocation}>
                      {rehearsalActivityTypeLabel(rehearsalQuery.data.activity_type)}
                    </ThemedText>
                  </ThemedView>
                  {rehearsalQuery.data.start_date_time && (
                    <ThemedView style={styles.rehearsalPrimaryInfo}>
                      <ThemedText type="smallBold">日時</ThemedText>
                      <ThemedText testID="rehearsal-info-datetime-date" style={styles.rehearsalDate}>
                        {formatDayHeader(new Date(rehearsalQuery.data.start_date_time))}
                      </ThemedText>
                      <ThemedText testID="rehearsal-info-datetime" style={styles.rehearsalTime}>
                        {formatTime(rehearsalQuery.data.start_date_time)}
                        {rehearsalQuery.data.end_date_time ? `〜${formatTime(rehearsalQuery.data.end_date_time)}` : ''}
                      </ThemedText>
                    </ThemedView>
                  )}
                  {rehearsalQuery.data.location && (
                    <ThemedView style={styles.rehearsalSecondaryInfo}>
                      <ThemedText type="smallBold">場所</ThemedText>
                      <ThemedText testID="rehearsal-info-location" style={styles.rehearsalLocation}>
                        {rehearsalQuery.data.location}
                      </ThemedText>
                    </ThemedView>
                  )}
                  <ThemedView style={styles.rehearsalDescription}>
                    <ThemedText type="smallBold">備考</ThemedText>
                    <ThemedText testID="rehearsal-info-description" style={styles.rehearsalDescriptionText}>
                      {rehearsalQuery.data.description?.trim() || 'なし'}
                    </ThemedText>
                  </ThemedView>
                  {canManageRehearsal && isOverdueScheduled(rehearsalQuery.data) && (
                    <ThemedText type="small" style={styles.overdueNotice} testID="rehearsal-overdue-notice">
                      {OVERDUE_SCHEDULED_MESSAGE}
                    </ThemedText>
                  )}
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
              {canAddTargets && (
                <ThemedView style={styles.compactCard} testID="unselected-members-panel">
                  <ThemedText type="smallBold">参加メンバーを追加</ThemedText>
                  {participantsQuery.isLoading && <ActivityIndicator testID="unselected-members-loading" />}
                  {!participantsQuery.isLoading && unselectedMembers.length === 0 && (
                    <ThemedText type="small" themeColor="textSecondary" testID="unselected-members-empty">
                      追加できるメンバーはいません。
                    </ThemedText>
                  )}
                  {unselectedMembers.map((participant) => {
                    const isChecked = selectedNewMemberIds.has(participant.subject_id);
                    const typeLabel = PARTICIPANT_TYPE_LABEL[participant.participant_type] ?? participant.participant_type;
                    const fullName = [participant.person_family_name, participant.person_given_name].filter(Boolean).join(' ');
                    const label = participant.display_name?.trim() || fullName || '名前未設定';

                    return (
                      <TouchableOpacity
                        key={participant.id}
                        testID={`unselected-member-${participant.subject_id}`}
                        onPress={() => toggleNewMember(participant.subject_id)}
                        style={styles.memberRow}
                        accessibilityRole="checkbox"
                        accessibilityState={{ checked: isChecked }}
                        accessibilityLabel={label}
                      >
                        <View
                          testID={`unselected-member-checkbox-${participant.subject_id}`}
                          style={[styles.checkbox, isChecked && styles.checkboxChecked]}
                        >
                          {isChecked && <ThemedText style={styles.checkboxMark}>✓</ThemedText>}
                        </View>
                        <ThemedText>{label}</ThemedText>
                        <ThemedText type="small" themeColor="textSecondary">
                          （{typeLabel}）
                        </ThemedText>
                      </TouchableOpacity>
                    );
                  })}
                  {unselectedMembers.length > 0 && (
                    <TouchableOpacity
                      testID="unselected-members-add"
                      onPress={handleAddSelectedMembers}
                      disabled={selectedNewMemberIds.size === 0 || addTargetsMutation.isPending}
                      accessibilityRole="button"
                      accessibilityLabel="選択したメンバーを追加する"
                      style={[
                        styles.actionButton,
                        styles.confirmButton,
                        (selectedNewMemberIds.size === 0 || addTargetsMutation.isPending) && styles.disabledButton,
                      ]}
                    >
                      {addTargetsMutation.isPending ? <ActivityIndicator color="#fff" /> : <ThemedText style={styles.actionButtonText}>追加する</ThemedText>}
                    </TouchableOpacity>
                  )}
                  {addTargetsMutation.isError && (
                    <ThemedText testID="unselected-members-add-error">{getErrorMessage(addTargetsMutation.error)}</ThemedText>
                  )}
                </ThemedView>
              )}
              {myRecord && (
                <ThemedView style={styles.compactCard} testID="attendance-my-record">
                  <ThemedView style={styles.myStatusRow}>
                    <ThemedText type="smallBold">あなたの出欠</ThemedText>
                    <ThemedText type="smallBold" testID="attendance-my-status">
                      {statusLabel(myRecord.status)}
                    </ThemedText>
                  </ThemedView>
                  {!showResponseForm && myRecord.remarks?.trim() ? (
                    <ThemedText type="small" testID="attendance-my-remarks">
                      備考：{myRecord.remarks}
                    </ThemedText>
                  ) : null}
                  {!showResponseForm && canChangeAnswer && (
                    <TouchableOpacity
                      testID="attendance-change-answer"
                      onPress={() => {
                        setRemarksDraft(myRecord.remarks ?? '');
                        setIsChangingAnswer(true);
                      }}
                      accessibilityRole="button"
                      accessibilityLabel="回答を変更する"
                    >
                      <ThemedText type="link">回答を変更する</ThemedText>
                    </TouchableOpacity>
                  )}
                  {!showResponseForm && responseOpen && !isUnanswered && responseDeadlinePassed && (
                    <ThemedText testID="attendance-response-deadline-passed" themeColor="textSecondary" type="small">
                      回答期限を過ぎたため、回答・変更はできません。
                    </ThemedText>
                  )}
                  {showResponseForm && (
                  <>
                  <ThemedText type="small">{isUnanswered ? '稽古の出欠を登録してください。' : '変更後の出欠を選んでください。'}</ThemedText>
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
                            respondMutation.mutate(
                              {
                                attendanceId: myRecord.id,
                                status: option,
                                remarks: remarksDraft.trim() || null,
                              },
                              { onSuccess: () => setIsChangingAnswer(false) }
                            )
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
                  {!isUnanswered && (
                    <TouchableOpacity
                      testID="attendance-change-answer-cancel"
                      onPress={() => setIsChangingAnswer(false)}
                      accessibilityRole="button"
                      accessibilityLabel="回答の変更をやめる"
                    >
                      <ThemedText type="link">変更しない</ThemedText>
                    </TouchableOpacity>
                  )}
                  {respondMutation.isError && (
                    <ThemedText testID="attendance-respond-error">{getErrorMessage(respondMutation.error)}</ThemedText>
                  )}
                  </>
                  )}
                </ThemedView>
              )}
            </ThemedView>
          }
          renderItem={({ item }) => (
            <AttendanceRow
              attendance={item}
              displayName={participantNameByPersonId.get(item.person_id) ?? '氏名未設定'}
              actualSelect={
                canRecordActual
                  ? {
                      // R9: every member's result can be recorded, whatever
                      // they answered; the answer stays on the badge.
                      value: pendingActual[item.id] ?? item.actual_status ?? null,
                      isOpen: openActualSelectId === item.id,
                      onToggle: () => setOpenActualSelectId((current) => (current === item.id ? null : item.id)),
                      onSelect: (status) => handleSelectActual(item.id, status),
                    }
                  : null
              }
            />
          )}
          ListFooterComponent={
            <ThemedView style={styles.commentsSection}>
              {canRecordActual && (
                <ThemedView style={styles.actualSaveArea}>
                  <TouchableOpacity
                    testID="attendance-actual-save"
                    onPress={handleSaveActual}
                    disabled={pendingActualCount === 0 || isSavingActual}
                    accessibilityRole="button"
                    accessibilityLabel="出欠を更新する"
                    style={[
                      styles.actionButton,
                      styles.confirmButton,
                      (pendingActualCount === 0 || isSavingActual) && styles.disabledButton,
                    ]}
                  >
                    {isSavingActual ? <ActivityIndicator color="#fff" /> : <ThemedText style={styles.actionButtonText}>更新</ThemedText>}
                  </TouchableOpacity>
                  {actualSaveError !== null && (
                    <ThemedText testID="attendance-actual-save-error">{getErrorMessage(actualSaveError)}</ThemedText>
                  )}
                </ThemedView>
              )}
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

type ActualSelectProps = {
  value: string | null;
  isOpen: boolean;
  onToggle: () => void;
  onSelect: (status: string) => void;
};

function AttendanceRow({
  attendance,
  displayName,
  actualSelect,
}: {
  attendance: RehearsalAttendance;
  displayName: string;
  actualSelect: ActualSelectProps | null;
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
      {actualSelect && (
        <View style={styles.actualSelect}>
          <TouchableOpacity
            testID={`attendance-actual-select-${attendance.id}`}
            onPress={actualSelect.onToggle}
            accessibilityRole="button"
            accessibilityLabel={`${displayName}の出欠を選択`}
            accessibilityState={{ expanded: actualSelect.isOpen }}
            style={styles.selectBox}
          >
            <ThemedText type="small">
              実績：{actualSelect.value ? ACTUAL_STATUS_LABELS[actualSelect.value] : '未記録'} ▼
            </ThemedText>
          </TouchableOpacity>
          {actualSelect.isOpen && (
            <View style={styles.selectOptions}>
              {ACTUAL_STATUS_OPTIONS.map((option) => (
                <TouchableOpacity
                  key={option}
                  testID={`attendance-actual-option-${attendance.id}-${option}`}
                  onPress={() => actualSelect.onSelect(option)}
                  accessibilityRole="button"
                  accessibilityLabel={ACTUAL_STATUS_LABELS[option]}
                  accessibilityState={{ selected: actualSelect.value === option }}
                  style={[styles.selectOption, actualSelect.value === option && styles.selectOptionSelected]}
                >
                  <ThemedText type="small">{ACTUAL_STATUS_LABELS[option]}</ThemedText>
                </TouchableOpacity>
              ))}
            </View>
          )}
        </View>
      )}
    </ThemedView>
  );
}

const styles = StyleSheet.create({
  overdueNotice: { color: '#b3261e' },
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
    minHeight: 36,
    paddingHorizontal: Spacing.two,
    paddingVertical: Spacing.one,
    borderRadius: 7,
    alignItems: 'center',
    justifyContent: 'center',
  },
  actionButtonText: { color: '#fff', fontWeight: '700', fontSize: 14, lineHeight: 18 },
  editButton: { backgroundColor: '#d99a00' },
  cancelButton: { backgroundColor: '#c62828' },
  confirmButton: { backgroundColor: '#2e7d32' },
  rehearsalPrimaryInfo: { gap: 2 },
  rehearsalSecondaryInfo: { gap: 2 },
  rehearsalDate: { fontSize: 17, lineHeight: 22, fontWeight: '700' },
  rehearsalTime: { fontSize: 21, lineHeight: 27, fontWeight: '800' },
  rehearsalLocation: { fontSize: 17, lineHeight: 22, fontWeight: '700' },
  rehearsalDescription: { gap: 2, marginTop: Spacing.one },
  rehearsalDescriptionText: { fontSize: 18, lineHeight: 24, fontWeight: '700' },
  summaryAttending: { color: '#2e7d32' },
  summaryNotAttending: { color: '#c62828' },
  summaryUnanswered: { color: '#777' },
  rosterStatusBadge: {
    minHeight: 24,
    paddingHorizontal: Spacing.one,
    paddingVertical: Spacing.half,
    alignSelf: 'flex-start',
  },
  rosterRemarks: { alignSelf: 'stretch' },
  myStatusRow: { flexDirection: 'row', alignItems: 'center', gap: Spacing.two },
  actualSelect: { alignSelf: 'stretch', gap: Spacing.half },
  selectBox: {
    borderWidth: 1,
    borderColor: '#b7b2c0',
    borderRadius: 6,
    paddingHorizontal: Spacing.two,
    paddingVertical: Spacing.one,
    backgroundColor: '#fff',
  },
  selectOptions: { borderWidth: 1, borderColor: '#e1dee6', borderRadius: 6, backgroundColor: '#fff' },
  selectOption: { paddingHorizontal: Spacing.two, paddingVertical: Spacing.one },
  selectOptionSelected: { backgroundColor: '#ece8f5' },
  actualSaveArea: { gap: Spacing.one, marginBottom: Spacing.three },
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
  responseButtonRow: { flexDirection: 'row', gap: Spacing.one, flexWrap: 'wrap', alignItems: 'flex-start' },
  responseButton: {
    minWidth: 88,
    minHeight: 36,
    paddingHorizontal: Spacing.two,
    paddingVertical: Spacing.one,
    borderRadius: 6,
    alignItems: 'center',
    justifyContent: 'center',
  },
  statusBadge: {
    minHeight: 30,
    paddingHorizontal: Spacing.two,
    paddingVertical: Spacing.one,
    borderRadius: 6,
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
