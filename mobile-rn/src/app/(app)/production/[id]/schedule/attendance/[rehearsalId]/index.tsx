import { useLocalSearchParams, useRouter } from 'expo-router';
import { useMemo, useState } from 'react';
import { ActivityIndicator, FlatList, StyleSheet, TouchableOpacity, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { ScheduleCommentList } from '@/components/schedule-comment-list';
import { ThemedText } from '@/components/themed-text';
import { ThemedTextInput } from '@/components/themed-text-input';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';
import { ACTUAL_STATUS_OPTIONS, attendanceSummary, responseOptionsForPhase, statusLabel } from '@/features/attendance/viewModel';
import { phaseForRehearsalStatus } from '@/features/attendance/phase';
import {
  useActivateRehearsal,
  useCancelRehearsal,
  useCompleteRehearsal,
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
import {
  useCreateTimetableItem,
  usePublishRehearsalTimetable,
  useRehearsalDraftTimetableItems,
} from '@/features/schedule/useTimetableAuthoring';
import { confirmAlert } from '@/utils/confirmAlert';
import { getErrorMessage } from '@/utils/errorMessage';
import type { Participant, RehearsalAttendance, ScheduleComment, TimetableItem } from '@/types/api';

const PARTICIPANT_TYPE_LABEL: Record<string, string> = { CAST: '出演者', STAFF: 'スタッフ' };

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

  const attendancesQuery = useRehearsalAttendances(rehearsalId, phase);
  const currentPersonQuery = useCurrentPerson();
  const respondMutation = useRespondRehearsalAttendance(rehearsalId, phase);
  const recordActualMutation = useRecordActualRehearsalAttendanceStatus(rehearsalId, phase);

  const participantsQuery = useParticipants(productionId);
  const addTargetsMutation = useAddRehearsalAttendanceTargets(rehearsalId, phase);
  const [selectedNewMemberIds, setSelectedNewMemberIds] = useState<Set<string>>(new Set());

  /**
   * 「未選択メンバー」= 現在このRehearsalのRehearsalAttendance対象に
   * なっていない、ACTIVEなPerson-subject Participant。Backend側で追加時に
   * 同じ母集団を再検証する（AddRehearsalAttendanceTargetsUseCase.php）ため、
   * ここでの絞り込みはUI表示専用で、権限・整合性チェックを肩代わりしない。
   */
  const eligibleMembers = (participantsQuery.data ?? []).filter(
    (participant): participant is Participant => participant.subject_type === 'PERSON' && participant.status === 'ACTIVE'
  );
  const currentTargetPersonIds = new Set((attendancesQuery.data ?? []).map((a) => a.person_id));
  const unselectedMembers = eligibleMembers.filter((participant) => !currentTargetPersonIds.has(participant.subject_id));

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

  const confirmRehearsal = useConfirmRehearsal(rehearsalId);
  const cancelRehearsal = useCancelRehearsal(rehearsalId);
  const activateRehearsal = useActivateRehearsal(rehearsalId);
  const completeRehearsal = useCompleteRehearsal(rehearsalId);
  const draftItemsQuery = useRehearsalDraftTimetableItems(rehearsalId);
  const createTimetableItem = useCreateTimetableItem(rehearsalId);
  const publishTimetable = usePublishRehearsalTimetable(rehearsalId);

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
  const summary = useMemo(
    () => attendanceSummary((attendancesQuery.data ?? []).map((a) => a.status)),
    [attendancesQuery.data]
  );

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
          data={attendancesQuery.data ?? []}
          keyExtractor={(a) => a.id}
          contentContainerStyle={styles.list}
          ListHeaderComponent={
            <ThemedView>
              <RehearsalManagementPanel
                rehearsalStatus={rehearsalQuery.data?.status}
                onEdit={() => router.push(`/production/${productionId}/schedule/attendance/${rehearsalId}/edit`)}
                onConfirm={() => confirmRehearsal.mutate()}
                isConfirmPending={confirmRehearsal.isPending}
                confirmError={confirmRehearsal.isError ? confirmRehearsal.error : null}
                onCancel={() =>
                  confirmAlert('稽古を中止する', 'この稽古を中止しますか？この操作は元に戻せません。', [
                    { text: 'キャンセル', style: 'cancel' },
                    { text: '中止する', style: 'destructive', onPress: () => cancelRehearsal.mutate() },
                  ])
                }
                isCancelPending={cancelRehearsal.isPending}
                cancelError={cancelRehearsal.isError ? cancelRehearsal.error : null}
                onActivate={() => activateRehearsal.mutate()}
                isActivatePending={activateRehearsal.isPending}
                activateError={activateRehearsal.isError ? activateRehearsal.error : null}
                onComplete={() => completeRehearsal.mutate()}
                isCompletePending={completeRehearsal.isPending}
                completeError={completeRehearsal.isError ? completeRehearsal.error : null}
                draftItems={draftItemsQuery.data}
                isDraftItemsLoading={draftItemsQuery.isLoading}
                onAddItem={(fields) => createTimetableItem.mutate(fields)}
                isAddItemPending={createTimetableItem.isPending}
                addItemError={createTimetableItem.isError ? createTimetableItem.error : null}
                onPublish={() => publishTimetable.mutate(undefined)}
                isPublishPending={publishTimetable.isPending}
                publishError={publishTimetable.isError ? publishTimetable.error : null}
                publishSuccess={publishTimetable.isSuccess}
              />
              <ThemedView style={styles.summaryCard} testID="attendance-summary">
                <ThemedText type="smallBold">出席状況サマリー</ThemedText>
                <ThemedText testID="attendance-summary-attending">出席 {summary.attending}名</ThemedText>
                <ThemedText testID="attendance-summary-not-attending">欠席 {summary.notAttending}名</ThemedText>
                <ThemedText testID="attendance-summary-unanswered">未回答 {summary.unanswered}名</ThemedText>
              </ThemedView>
              <ThemedView style={styles.summaryCard} testID="unselected-members-panel">
                <ThemedText type="smallBold">未選択メンバー</ThemedText>
                {participantsQuery.isLoading && <ActivityIndicator testID="unselected-members-loading" />}
                {!participantsQuery.isLoading && unselectedMembers.length === 0 && (
                  <ThemedText type="small" themeColor="textSecondary" testID="unselected-members-empty">
                    未選択のメンバーはいません。
                  </ThemedText>
                )}
                {unselectedMembers.map((participant) => {
                  const isChecked = selectedNewMemberIds.has(participant.subject_id);
                  const typeLabel = PARTICIPANT_TYPE_LABEL[participant.participant_type] ?? participant.participant_type;

                  return (
                    <TouchableOpacity
                      key={participant.id}
                      testID={`unselected-member-${participant.subject_id}`}
                      onPress={() => toggleNewMember(participant.subject_id)}
                      style={styles.memberRow}
                    >
                      <View
                        testID={`unselected-member-checkbox-${participant.subject_id}`}
                        style={[styles.checkbox, isChecked && styles.checkboxChecked]}
                      >
                        {isChecked && <ThemedText style={styles.checkboxMark}>✓</ThemedText>}
                      </View>
                      <ThemedText>Person ID: {participant.subject_id}</ThemedText>
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
                  >
                    {addTargetsMutation.isPending ? <ActivityIndicator /> : <ThemedText type="link">追加する</ThemedText>}
                  </TouchableOpacity>
                )}
                {addTargetsMutation.isError && (
                  <ThemedText testID="unselected-members-add-error">{getErrorMessage(addTargetsMutation.error)}</ThemedText>
                )}
              </ThemedView>
              {myRecord && (
                <ThemedView style={styles.myCard} testID="attendance-my-record">
                  <ThemedText type="smallBold">あなたの回答</ThemedText>
                  <ThemedText testID="attendance-my-status">{statusLabel(myRecord.status)}</ThemedText>
                  <ThemedView style={styles.buttonRow}>
                    {responseOptionsForPhase(phase).map((option) => (
                      <TouchableOpacity
                        key={option}
                        testID={`attendance-respond-${option}`}
                        onPress={() => respondMutation.mutate({ attendanceId: myRecord.id, status: option })}
                        disabled={respondMutation.isPending}
                      >
                        <ThemedText type="link">{statusLabel(option)}</ThemedText>
                      </TouchableOpacity>
                    ))}
                  </ThemedView>
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
              phase={phase}
              onRecordActual={(status) => recordActualMutation.mutate({ attendanceId: item.id, status })}
              isRecordPending={recordActualMutation.isPending}
              recordError={
                recordActualMutation.isError && recordActualMutation.variables?.attendanceId === item.id
                  ? recordActualMutation.error
                  : null
              }
            />
          )}
          ListFooterComponent={
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
                >
                  {postComment.isPending ? <ActivityIndicator /> : <ThemedText type="link">投稿する</ThemedText>}
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
  phase,
  onRecordActual,
  isRecordPending,
  recordError,
}: {
  attendance: RehearsalAttendance;
  phase: string;
  onRecordActual: (status: string) => void;
  isRecordPending: boolean;
  recordError: unknown;
}) {
  return (
    <ThemedView style={styles.rosterRow} testID="attendance-roster-row">
      <ThemedText type="small" themeColor="textSecondary">
        {attendance.person_id}
      </ThemedText>
      <ThemedText>{statusLabel(attendance.status)}</ThemedText>

      {phase === 'ATTENDANCE_CONFIRMATION' && (
        <ThemedView style={styles.buttonRow}>
          {ACTUAL_STATUS_OPTIONS.map((option) => (
            <TouchableOpacity
              key={option}
              testID={`attendance-record-actual-${attendance.id}-${option}`}
              onPress={() => onRecordActual(option)}
              disabled={isRecordPending}
            >
              <ThemedText type="link">{statusLabel(option)}</ThemedText>
            </TouchableOpacity>
          ))}
        </ThemedView>
      )}
      {recordError !== null && (
        <ThemedText testID={`attendance-record-actual-error-${attendance.id}`}>{getErrorMessage(recordError)}</ThemedText>
      )}
    </ThemedView>
  );
}

/**
 * docs/04-HomeRoleBasedMenu.md §07の稽古管理: 内容追加(TimetableItem)・
 * 公開(TimetableVersion publish)・確定(Rehearsal confirm)。§24/§25と同じ
 * 既存方針で、権限のないPersonにもこのPanel自体は表示し、実際のMutationが
 * Backendの403をそのまま表示する（Client側でRoleを複製しない）。
 */
function RehearsalManagementPanel({
  rehearsalStatus,
  onEdit,
  onConfirm,
  isConfirmPending,
  confirmError,
  onCancel,
  isCancelPending,
  cancelError,
  onActivate,
  isActivatePending,
  activateError,
  onComplete,
  isCompletePending,
  completeError,
  draftItems,
  isDraftItemsLoading,
  onAddItem,
  isAddItemPending,
  addItemError,
  onPublish,
  isPublishPending,
  publishError,
  publishSuccess,
}: {
  rehearsalStatus: string | undefined;
  onEdit: () => void;
  onConfirm: () => void;
  isConfirmPending: boolean;
  confirmError: unknown;
  onCancel: () => void;
  isCancelPending: boolean;
  cancelError: unknown;
  onActivate: () => void;
  isActivatePending: boolean;
  activateError: unknown;
  onComplete: () => void;
  isCompletePending: boolean;
  completeError: unknown;
  draftItems: TimetableItem[] | undefined;
  isDraftItemsLoading: boolean;
  onAddItem: (fields: { title: string; startDateTime: string; category?: string; venue?: string }) => void;
  isAddItemPending: boolean;
  addItemError: unknown;
  onPublish: () => void;
  isPublishPending: boolean;
  publishError: unknown;
  publishSuccess: boolean;
}) {
  const [itemTitle, setItemTitle] = useState('');
  const [itemDate, setItemDate] = useState('');
  const [itemTime, setItemTime] = useState('');
  const [itemVenue, setItemVenue] = useState('');

  function handleAddItem() {
    if (!itemTitle.trim() || !itemDate || !itemTime) return;

    onAddItem({
      title: itemTitle.trim(),
      startDateTime: `${itemDate}T${itemTime}:00+09:00`,
      venue: itemVenue.trim() || undefined,
    });
    setItemTitle('');
    setItemVenue('');
  }

  return (
    <ThemedView style={managementStyles.panel} testID="rehearsal-management-panel">
      <ThemedText type="subtitle">稽古管理</ThemedText>

      <ThemedView style={managementStyles.section}>
        <ThemedText type="smallBold">内容（タイムテーブル項目）</ThemedText>
        {isDraftItemsLoading && <ActivityIndicator testID="draft-items-loading" />}
        {(draftItems?.length ?? 0) > 0 && (
          <ThemedView testID="draft-items-list" style={managementStyles.itemList}>
            {draftItems?.map((item) => (
              <ThemedText key={item.id} type="small" testID={`draft-item-${item.id}`}>
                {item.title}
                {item.venue ? `（${item.venue}）` : ''}
              </ThemedText>
            ))}
          </ThemedView>
        )}

        <ThemedTextInput
          testID="timetable-item-title"
          placeholder="項目名"
          value={itemTitle}
          onChangeText={setItemTitle}
          style={managementStyles.input}
        />
        <ThemedView style={managementStyles.row}>
          <ThemedTextInput
            testID="timetable-item-date"
            placeholder="2026-09-10"
            value={itemDate}
            onChangeText={setItemDate}
            autoCapitalize="none"
            style={[managementStyles.input, managementStyles.flexInput]}
          />
          <ThemedTextInput
            testID="timetable-item-time"
            placeholder="18:00"
            value={itemTime}
            onChangeText={setItemTime}
            autoCapitalize="none"
            style={[managementStyles.input, managementStyles.flexInput]}
          />
        </ThemedView>
        <ThemedTextInput
          testID="timetable-item-venue"
          placeholder="場所（任意）"
          value={itemVenue}
          onChangeText={setItemVenue}
          style={managementStyles.input}
        />
        <TouchableOpacity
          testID="timetable-item-add"
          onPress={handleAddItem}
          disabled={!itemTitle.trim() || !itemDate || !itemTime || isAddItemPending}
          style={managementStyles.secondaryButton}
        >
          {isAddItemPending ? <ActivityIndicator /> : <ThemedText type="link">項目を追加する</ThemedText>}
        </TouchableOpacity>
        {addItemError !== null && <ThemedText testID="timetable-item-add-error">{getErrorMessage(addItemError)}</ThemedText>}
      </ThemedView>

      <ThemedView style={managementStyles.section}>
        <TouchableOpacity testID="rehearsal-publish" onPress={onPublish} disabled={isPublishPending} style={managementStyles.secondaryButton}>
          {isPublishPending ? <ActivityIndicator /> : <ThemedText type="link">内容を公開する</ThemedText>}
        </TouchableOpacity>
        {publishSuccess && (
          <ThemedText testID="rehearsal-publish-success" type="small" themeColor="textSecondary">
            公開しました。
          </ThemedText>
        )}
        {publishError !== null && <ThemedText testID="rehearsal-publish-error">{getErrorMessage(publishError)}</ThemedText>}
      </ThemedView>

      {(rehearsalStatus === 'DRAFT' || rehearsalStatus === 'SCHEDULED' || rehearsalStatus === 'CONFIRMED' || rehearsalStatus === 'ACTIVE') && (
        <ThemedView style={managementStyles.section}>
          <TouchableOpacity testID="rehearsal-edit" onPress={onEdit} style={managementStyles.secondaryButton}>
            <ThemedText type="link">編集</ThemedText>
          </TouchableOpacity>
          {(rehearsalStatus === 'DRAFT' || rehearsalStatus === 'SCHEDULED') && (
            <>
              <TouchableOpacity
                testID="rehearsal-confirm"
                onPress={onConfirm}
                disabled={isConfirmPending}
                style={managementStyles.secondaryButton}
              >
                {isConfirmPending ? <ActivityIndicator /> : <ThemedText type="link">稽古情報を確定する</ThemedText>}
              </TouchableOpacity>
              {confirmError !== null && <ThemedText testID="rehearsal-confirm-error">{getErrorMessage(confirmError)}</ThemedText>}
            </>
          )}
          {rehearsalStatus === 'CONFIRMED' && (
            <>
              <TouchableOpacity
                testID="rehearsal-activate"
                onPress={onActivate}
                disabled={isActivatePending}
                style={managementStyles.secondaryButton}
              >
                {isActivatePending ? <ActivityIndicator /> : <ThemedText type="link">稽古を開始する</ThemedText>}
              </TouchableOpacity>
              {activateError !== null && <ThemedText testID="rehearsal-activate-error">{getErrorMessage(activateError)}</ThemedText>}
            </>
          )}
          {rehearsalStatus === 'ACTIVE' && (
            <>
              <TouchableOpacity
                testID="rehearsal-complete"
                onPress={onComplete}
                disabled={isCompletePending}
                style={managementStyles.secondaryButton}
              >
                {isCompletePending ? <ActivityIndicator /> : <ThemedText type="link">実施済みにする</ThemedText>}
              </TouchableOpacity>
              {completeError !== null && <ThemedText testID="rehearsal-complete-error">{getErrorMessage(completeError)}</ThemedText>}
            </>
          )}
          <TouchableOpacity testID="rehearsal-cancel" onPress={onCancel} disabled={isCancelPending} style={managementStyles.secondaryButton}>
            {isCancelPending ? <ActivityIndicator /> : <ThemedText type="link">中止する</ThemedText>}
          </TouchableOpacity>
          {cancelError !== null && <ThemedText testID="rehearsal-cancel-error">{getErrorMessage(cancelError)}</ThemedText>}
        </ThemedView>
      )}
    </ThemedView>
  );
}

const managementStyles = StyleSheet.create({
  panel: {
    borderWidth: 1,
    borderColor: '#e1dee6',
    borderRadius: 10,
    padding: Spacing.three,
    gap: Spacing.three,
    marginBottom: Spacing.three,
  },
  section: { gap: Spacing.two },
  itemList: { gap: Spacing.half },
  input: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    paddingHorizontal: Spacing.three,
    paddingVertical: Spacing.two,
    fontSize: 14,
  },
  row: { flexDirection: 'row', gap: Spacing.two },
  flexInput: { flex: 1 },
  secondaryButton: { alignItems: 'flex-start' },
});

const styles = StyleSheet.create({
  safeArea: { flex: 1 },
  backRow: { paddingHorizontal: Spacing.four, paddingVertical: Spacing.two },
  centered: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: Spacing.four, gap: Spacing.two },
  list: { padding: Spacing.four, gap: Spacing.two },
  myCard: {
    borderWidth: 1,
    borderColor: '#e1dee6',
    borderRadius: 10,
    padding: Spacing.three,
    gap: Spacing.two,
    marginBottom: Spacing.three,
  },
  summaryCard: {
    borderWidth: 1,
    borderColor: '#e1dee6',
    borderRadius: 10,
    padding: Spacing.three,
    gap: Spacing.one,
    marginBottom: Spacing.three,
  },
  rosterRow: {
    borderWidth: 1,
    borderColor: '#e1dee6',
    borderRadius: 10,
    padding: Spacing.three,
    gap: Spacing.one,
  },
  buttonRow: { flexDirection: 'row', gap: Spacing.three },
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
