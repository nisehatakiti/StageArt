import { useLocalSearchParams, useRouter } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, ScrollView, StyleSheet, TouchableOpacity, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { ThemedText } from '@/components/themed-text';
import { ThemedTextInput } from '@/components/themed-text-input';
import { Radius, Spacing } from '@/constants/theme';
import { useAuth } from '@/auth/AuthContext';
import { confirmRehearsal } from '@/features/attendance/api';
import { useCreateRehearsal } from '@/features/attendance/useRehearsals';
import { useParticipants } from '@/features/participant/useParticipant';
import { useCurrentPerson } from '@/features/person/useCurrentPerson';
import { getErrorMessage } from '@/utils/errorMessage';
import type { Participant } from '@/types/api';

/** Same label map as productions/[id]/participants.tsx - Participant
 * type is CAST/STAFF only (ParticipantType.php), not extended here. */
const PARTICIPANT_TYPE_LABEL: Record<string, string> = { CAST: '出演者', STAFF: 'スタッフ' };

/**
 * docs/04-HomeRoleBasedMenu.md §07の稽古管理「日程作成・調整」: 日時・場所を
 * 入力してRehearsalを作成する。作成直後はDRAFT状態で、出欠確認
 * (SCHEDULE_ADJUSTMENT phase)対象者のRehearsalAttendanceがBackend側で
 * 自動作成される (CreateRehearsalUseCase.php) - このFormは日時設定・場所
 * 設定のみを担当し、内容 (Timetable Item) の追加は作成後の稽古詳細画面
 * で行う。
 */
export default function CreateRehearsalScreen() {
  const { id: productionId } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();
  const { apiClient } = useAuth();
  const createRehearsal = useCreateRehearsal(productionId);
  const participantsQuery = useParticipants(productionId);
  const currentPersonQuery = useCurrentPerson();

  const [title, setTitle] = useState('');
  const [date, setDate] = useState('');
  const [time, setTime] = useState('');
  const [endTime, setEndTime] = useState('');
  const [location, setLocation] = useState('');

  /**
   * 稽古の参加対象は、稽古作成時に選択されたProductionメンバーのみ
   * (CreateRehearsalUseCase.phpはもう「Production Participant全員」を
   * 自動対象にしない - Backend側の対応する変更を参照)。選択対象は
   * ACTIVEかつPerson-subjectのParticipantのみ（Backendの
   * activeProductionMemberPersonIds()と同じ母集団）。
   *
   * 初期選択状態: CAST（participant_type === 'CAST'）のみチェック済み、
   * STAFFは未チェック - あくまで初期状態の違いであり、「選択対象をCASTに
   * 限定する」という新しいDomainルールではない。ユーザーは個別に
   * STAFFを選択したり、全選択/全選択解除で対象を変更できる。CAST/STAFF
   * の判定は既存のParticipant.participant_typeをそのまま利用し、Person
   * 自身のRoleやOrganization MembershipのRoleとは無関係。
   */
  const eligibleMembers = (participantsQuery.data ?? []).filter(
    (participant): participant is Participant => participant.subject_type === 'PERSON' && participant.status === 'ACTIVE'
  );
  const [selectedPersonIds, setSelectedPersonIds] = useState<Set<string>>(new Set());
  // Tracks which fetch of participantsQuery.data the current selection was
  // seeded from, so the "default to CAST selected" initialization happens
  // exactly once per fresh fetch without an Effect (React's own "adjusting
  // state during render" pattern - https://react.dev/learn/you-might-not-need-an-effect).
  const [selectionSeededFrom, setSelectionSeededFrom] = useState<typeof participantsQuery.data>(undefined);

  if (participantsQuery.data && participantsQuery.data !== selectionSeededFrom) {
    setSelectionSeededFrom(participantsQuery.data);
    setSelectedPersonIds(
      new Set(eligibleMembers.filter((participant) => participant.participant_type === 'CAST').map((participant) => participant.subject_id))
    );
  }

  function toggleMember(personId: string) {
    setSelectedPersonIds((current) => {
      const next = new Set(current);
      if (next.has(personId)) {
        next.delete(personId);
      } else {
        next.add(personId);
      }
      return next;
    });
  }

  function selectAllMembers() {
    setSelectedPersonIds(new Set(eligibleMembers.map((participant) => participant.subject_id)));
  }

  function deselectAllMembers() {
    setSelectedPersonIds(new Set());
  }
  /**
   * UI-only choice of whether to confirm right after creation - kept as
   * its own boolean rather than a Domain RehearsalStatus value, since the
   * screen's「調整」「確定」labels are not the same thing as SCHEDULED/
   * CONFIRMED (see this Phase's report §3). create() itself always
   * starts a Rehearsal at SCHEDULED (Rehearsal.php has no way to create
   * directly into CONFIRMED); choosing「確定」here means "create, then
   * call the existing confirm endpoint" - two already-existing calls
   * chained by this screen, not a new Backend capability.
   */
  const [confirmOnCreate, setConfirmOnCreate] = useState(false);
  const [isConfirming, setIsConfirming] = useState(false);

  async function handleSubmit() {
    const startDateTime = date && time ? `${date}T${time}:00+09:00` : undefined;
    const endDateTime = date && endTime ? `${date}T${endTime}:00+09:00` : undefined;

    const rehearsal = await createRehearsal.mutateAsync({
      title: title.trim(),
      startDateTime,
      endDateTime,
      timezone: 'Asia/Tokyo',
      location: location.trim() || undefined,
      targetPersonIds: Array.from(selectedPersonIds),
    });

    if (confirmOnCreate) {
      setIsConfirming(true);
      try {
        await confirmRehearsal(apiClient, rehearsal.id);
      } catch {
        // Creation already succeeded (Rehearsal exists as 調整中/SCHEDULED).
        // No new recovery flow is added here - the existing "稽古情報を
        // 確定する" button on the detail screen (RehearsalManagementPanel)
        // already covers retrying the confirm step.
      } finally {
        setIsConfirming(false);
      }
    }

    router.replace(`/production/${productionId}/schedule/attendance/${rehearsal.id}`);
  }

  return (
    <SafeAreaView style={styles.safeArea}>
      <ScrollView contentContainerStyle={styles.container}>
        <ThemedText type="title" style={styles.title}>
          稽古を作成
        </ThemedText>

        <ThemedText type="small" themeColor="textSecondary">
          稽古名
        </ThemedText>
        <ThemedTextInput testID="rehearsal-create-title" placeholder="稽古名" value={title} onChangeText={setTitle} style={styles.input} />

        <ThemedText type="small" themeColor="textSecondary">
          ステータス
        </ThemedText>
        <View style={styles.statusRow}>
          <TouchableOpacity
            testID="rehearsal-create-status-adjustment"
            onPress={() => setConfirmOnCreate(false)}
            style={[styles.statusOption, !confirmOnCreate && styles.statusOptionSelected]}
          >
            <ThemedText style={!confirmOnCreate ? styles.statusOptionTextSelected : undefined}>調整</ThemedText>
          </TouchableOpacity>
          <TouchableOpacity
            testID="rehearsal-create-status-confirmed"
            onPress={() => setConfirmOnCreate(true)}
            style={[styles.statusOption, confirmOnCreate && styles.statusOptionSelected]}
          >
            <ThemedText style={confirmOnCreate ? styles.statusOptionTextSelected : undefined}>確定</ThemedText>
          </TouchableOpacity>
        </View>

        <ThemedText type="small" themeColor="textSecondary">
          日付（YYYY-MM-DD）
        </ThemedText>
        <ThemedTextInput
          testID="rehearsal-create-date"
          placeholder="2026-09-10"
          value={date}
          onChangeText={setDate}
          autoCapitalize="none"
          style={styles.input}
        />

        <ThemedText type="small" themeColor="textSecondary">
          開始時刻（HH:mm）
        </ThemedText>
        <ThemedTextInput
          testID="rehearsal-create-time"
          placeholder="18:00"
          value={time}
          onChangeText={setTime}
          autoCapitalize="none"
          style={styles.input}
        />

        <ThemedText type="small" themeColor="textSecondary">
          終了時刻（HH:mm）
        </ThemedText>
        <ThemedTextInput
          testID="rehearsal-create-end-time"
          placeholder="20:00"
          value={endTime}
          onChangeText={setEndTime}
          autoCapitalize="none"
          style={styles.input}
        />

        <ThemedText type="small" themeColor="textSecondary">
          場所
        </ThemedText>
        <ThemedTextInput testID="rehearsal-create-location" placeholder="○○スタジオ" value={location} onChangeText={setLocation} style={styles.input} />

        <ThemedText type="small" themeColor="textSecondary">
          参加メンバー
        </ThemedText>
        <View style={styles.memberSelectRow}>
          <TouchableOpacity testID="rehearsal-create-select-all" onPress={selectAllMembers}>
            <ThemedText type="link">全選択</ThemedText>
          </TouchableOpacity>
          <TouchableOpacity testID="rehearsal-create-deselect-all" onPress={deselectAllMembers}>
            <ThemedText type="link">全選択解除</ThemedText>
          </TouchableOpacity>
        </View>

        {participantsQuery.isLoading && <ActivityIndicator testID="rehearsal-create-members-loading" />}

        {!participantsQuery.isLoading && eligibleMembers.length === 0 && (
          <ThemedText type="small" themeColor="textSecondary" testID="rehearsal-create-members-empty">
            参加可能なメンバーがいません。
          </ThemedText>
        )}

        {eligibleMembers.map((participant) => {
          const isSelf = participant.subject_id === currentPersonQuery.data?.id;
          const label = isSelf ? 'あなた' : `Person ID: ${participant.subject_id}`;
          const typeLabel = PARTICIPANT_TYPE_LABEL[participant.participant_type] ?? participant.participant_type;
          const isChecked = selectedPersonIds.has(participant.subject_id);

          return (
            <TouchableOpacity
              key={participant.id}
              testID={`rehearsal-create-member-${participant.subject_id}`}
              onPress={() => toggleMember(participant.subject_id)}
              style={styles.memberRow}
            >
              <View
                testID={`rehearsal-create-member-checkbox-${participant.subject_id}`}
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

        {createRehearsal.isError && <ThemedText style={styles.error}>{getErrorMessage(createRehearsal.error)}</ThemedText>}

        <TouchableOpacity
          testID="rehearsal-create-submit"
          onPress={handleSubmit}
          disabled={!title.trim() || createRehearsal.isPending || isConfirming}
          style={[styles.button, (!title.trim() || createRehearsal.isPending || isConfirming) && styles.buttonDisabled]}
        >
          {createRehearsal.isPending || isConfirming ? (
            <ActivityIndicator color="#fff" />
          ) : (
            <ThemedText style={styles.buttonText}>作成する</ThemedText>
          )}
        </TouchableOpacity>
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safeArea: { flex: 1 },
  container: { padding: Spacing.four, gap: Spacing.two },
  title: { fontSize: 22, lineHeight: 28, marginBottom: Spacing.two },
  input: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    paddingHorizontal: Spacing.three,
    paddingVertical: Spacing.two,
    fontSize: 16,
    marginBottom: Spacing.two,
  },
  button: {
    backgroundColor: '#4a3f7a',
    borderRadius: Radius.medium,
    paddingVertical: Spacing.three,
    alignItems: 'center',
    marginTop: Spacing.two,
  },
  buttonDisabled: { opacity: 0.6 },
  buttonText: { color: '#fff', fontWeight: '600' },
  error: { color: '#a6483a' },
  statusRow: { flexDirection: 'row', gap: Spacing.two, marginBottom: Spacing.two },
  statusOption: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: Radius.medium,
    paddingHorizontal: Spacing.three,
    paddingVertical: Spacing.two,
  },
  statusOptionSelected: { backgroundColor: '#4a3f7a', borderColor: '#4a3f7a' },
  statusOptionTextSelected: { color: '#fff', fontWeight: '600' },
  memberSelectRow: { flexDirection: 'row', gap: Spacing.three, marginBottom: Spacing.one },
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
