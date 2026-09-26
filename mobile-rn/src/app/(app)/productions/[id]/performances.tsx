import { useLocalSearchParams, type Href } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, ScrollView, StyleSheet, TouchableOpacity, View } from 'react-native';

import { ApiError } from '@/api/errors';
import { ThemedText } from '@/components/themed-text';
import { FormInput } from '@/components/form-input';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import { useCancelPerformance, useCreatePerformance, usePerformances, useUpdatePerformance } from '@/features/performance/usePerformances';
import { useProduction } from '@/features/production/useProductions';
import { useProductionOrganization } from '@/features/production/useProductionOrganization';
import type { Performance } from '@/types/api';
import { getErrorMessage } from '@/utils/errorMessage';

const STATUS_LABEL: Record<string, string> = {
  PUBLISHED: '公開中',
  SOLD_OUT: '満席',
  FINISHED: '終了',
  CANCELLED: '中止',
};

/** Phase 6: the manually-settable Status options exposed here. CANCELLED
 * is deliberately excluded - it already has its own dedicated "中止"
 * button (`cancelPerformance`, idempotency-guarded with its own error
 * message), and offering it a second way through this generic selector
 * would let a manager bypass that guard without gaining anything.
 * Performance::changeStatus() itself enforces no transition graph beyond
 * "not from CANCELLED" (see its own docblock: "no strict transition
 * graph is mandated among PUBLISHED/SOLD_OUT/FINISHED"), so any of
 * these three may be selected from any of the other two.
 *
 * StageArt全体DRAFT廃止 instruction: DRAFT removed from this list -
 * StageArt does not use DRAFT to gate public/private visibility, and a
 * Performance now starts PUBLISHED (see Performance::create()). */
const EDITABLE_STATUS_OPTIONS = ['PUBLISHED', 'SOLD_OUT', 'FINISHED'] as const;

type EditState = {
  performanceDate: string;
  startTime: string;
  endTime: string;
  capacity: string;
  remarks: string;
  symbol: string;
  status: string;
};

function toEditState(performance: Performance): EditState {
  return {
    performanceDate: performance.performance_date,
    startTime: performance.start_time.slice(0, 5),
    endTime: performance.end_time?.slice(0, 5) ?? '',
    capacity: String(performance.capacity),
    remarks: performance.remarks ?? '',
    symbol: performance.symbol ?? '',
    status: performance.status,
  };
}

/**
 * StageArt Phase 2 Performance基盤 §22/§23/§24 + StageArt UI再構成
 * instruction (this round, ユーザー向け表記統一「公演回」→「公演スケジュール」):
 * 公演スケジュール管理 - Performance
 * list (公演日/開演時刻/終演予定時刻/定員/Status, plus 記号/備考), create
 * form (定員 pre-filled from Production.capacity, per §23), and inline
 * edit. "中止" (Status -> CANCELLED) is the one delete-like, dedicated,
 * idempotency-guarded action with its own button; a cancelled Performance
 * stays in the list as history, never physically removed (§24). Phase 6:
 * the inline edit row also exposes DRAFT/PUBLISHED/SOLD_OUT/FINISHED as a
 * direct Status selector (UpdatePerformanceUseCase already accepted this
 * field - Performance::changeStatus() itself enforces no transition graph
 * among these four, only that CANCELLED is terminal - this was simply
 * never sent by this screen before).
 */
export default function ProductionPerformancesScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const productionQuery = useProduction(id);
  const production = productionQuery.data;
  const { organization } = useProductionOrganization(production);
  const performancesQuery = usePerformances(id);
  const createPerformance = useCreatePerformance(id);
  const cancelPerformance = useCancelPerformance(id);

  const isPrimaryManager = !!production?.is_primary_manager;
  const canManage = isPrimaryManager || production?.delegate_role === 'PERFORMANCE_MANAGER';

  const [editingId, setEditingId] = useState<string | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const [newDate, setNewDate] = useState('');
  const [newStartTime, setNewStartTime] = useState('');
  const [newEndTime, setNewEndTime] = useState('');
  const [newCapacity, setNewCapacity] = useState(production?.capacity != null ? String(production.capacity) : '');
  const [newRemarks, setNewRemarks] = useState('');
  const [newSymbol, setNewSymbol] = useState('');

  const performances = performancesQuery.data ?? [];

  const breadcrumbs = [
    { label: 'StageArt', href: '/dashboard' as Href },
    { label: '団体', href: '/organizations' as Href },
    ...(organization ? [{ label: organization.name, href: `/organizations/${organization.id}` as Href }] : []),
    ...(organization ? [{ label: '公演', href: `/organizations/${organization.id}/productions` as Href }] : []),
    { label: production?.name ?? '...', href: `/productions/${id}` as Href },
    { label: '公演スケジュール管理' },
  ];

  async function handleCreate() {
    setErrorMessage(null);
    try {
      await createPerformance.mutateAsync({
        performanceDate: newDate.trim(),
        startTime: newStartTime.trim(),
        endTime: newEndTime.trim() || undefined,
        capacity: newCapacity.trim() ? Number(newCapacity.trim()) : undefined,
        remarks: newRemarks.trim() || undefined,
        symbol: newSymbol.trim() || undefined,
      });
      setNewDate('');
      setNewStartTime('');
      setNewEndTime('');
      setNewRemarks('');
      setNewSymbol('');
    } catch (error) {
      if (error instanceof ApiError && error.code === 'stageart_performance_duplicate_datetime') {
        setErrorMessage('同じ公演日・開演時刻の公演スケジュールは登録できません。');
      } else {
        setErrorMessage(getErrorMessage(error));
      }
    }
  }

  async function handleCancel(performanceId: string) {
    setErrorMessage(null);
    try {
      await cancelPerformance.mutateAsync(performanceId);
    } catch (error) {
      setErrorMessage(getErrorMessage(error));
    }
  }

  if (productionQuery.isLoading) {
    return (
      <>
        <ActivityIndicator testID="production-performances-loading" />
      </>
    );
  }

  if (!production) {
    return (
      <>
        <ThemedText testID="production-performances-not-found">この公演が見つかりません。</ThemedText>
      </>
    );
  }

  if (!canManage) {
    return (
      <>
        <ThemedText testID="production-performances-forbidden">
          公演スケジュール管理はPrimaryManagerまたは公演スケジュール管理の権限を持つ担当者のみ利用できます。
        </ThemedText>
      </>
    );
  }

  return (
    <>
      <ThemedText type="title" style={styles.pageTitle}>
        公演スケジュール管理
      </ThemedText>

      {performancesQuery.isLoading && <ActivityIndicator testID="production-performances-list-loading" />}
      {performancesQuery.isError && (
        <ThemedText testID="production-performances-list-error">{getErrorMessage(performancesQuery.error)}</ThemedText>
      )}
      {!performancesQuery.isLoading && !performancesQuery.isError && performances.length === 0 && (
        <ThemedText testID="production-performances-empty" themeColor="textSecondary">
          まだ公演スケジュールがありません。
        </ThemedText>
      )}

      {performances.length > 0 && (
        <View style={styles.list} testID="production-performances-list">
          {performances.map((performance) =>
            editingId === performance.id ? (
              <PerformanceEditRow
                key={performance.id}
                initialEdit={toEditState(performance)}
                onCancelEdit={() => setEditingId(null)}
                performanceId={performance.id}
                onSaved={() => setEditingId(null)}
                onError={setErrorMessage}
              />
            ) : (
              <View key={performance.id} style={styles.row} testID={`performance-row-${performance.id}`}>
                <ThemedText style={styles.colDate}>{performance.performance_date}</ThemedText>
                <ThemedText style={styles.colTime}>{performance.start_time.slice(0, 5)}</ThemedText>
                <ThemedText style={styles.colTime}>{performance.end_time?.slice(0, 5) ?? '-'}</ThemedText>
                <ThemedText style={styles.colCapacity}>{performance.capacity}</ThemedText>
                <ThemedText style={styles.colStatus}>{STATUS_LABEL[performance.status] ?? performance.status}</ThemedText>
                <View style={styles.actionButtons}>
                  <TouchableOpacity
                    testID={`performance-edit-${performance.id}`}
                    onPress={() => setEditingId(performance.id)}
                    disabled={performance.status === 'CANCELLED'}
                  >
                    <ThemedText type="link">編集</ThemedText>
                  </TouchableOpacity>
                  <TouchableOpacity
                    testID={`performance-cancel-${performance.id}`}
                    onPress={() => handleCancel(performance.id)}
                    disabled={performance.status === 'CANCELLED' || cancelPerformance.isPending}
                  >
                    <ThemedText type="link">中止</ThemedText>
                  </TouchableOpacity>
                </View>
              </View>
            )
          )}
        </View>
      )}

      <ThemedText type="subtitle" style={styles.sectionTitle}>
        公演スケジュールを追加
      </ThemedText>

      <ThemedText type="small" themeColor="textSecondary">
        公演日
      </ThemedText>
      <FormInput kind="date" testID="production-performances-new-date" value={newDate} onChangeText={setNewDate} style={[styles.input, styles.inputDate]} />

      <ThemedText type="small" themeColor="textSecondary">
        開演時刻
      </ThemedText>
      <FormInput
        kind="time"
        testID="production-performances-new-start-time"
        value={newStartTime}
        onChangeText={setNewStartTime}
        placeholder="13:00"
        style={[styles.input, styles.inputTime]}
      />

      <ThemedText type="small" themeColor="textSecondary">
        終演予定時刻（任意）
      </ThemedText>
      <FormInput
        kind="time"
        testID="production-performances-new-end-time"
        value={newEndTime}
        onChangeText={setNewEndTime}
        placeholder="15:00"
        style={[styles.input, styles.inputTime]}
      />

      <ThemedText type="small" themeColor="textSecondary">
        定員
      </ThemedText>
      <FormInput
        testID="production-performances-new-capacity"
        value={newCapacity}
        onChangeText={setNewCapacity}
        keyboardType="number-pad"
        style={[styles.input, styles.inputNumber]}
      />

      <ThemedText type="small" themeColor="textSecondary">
        記号（任意）
      </ThemedText>
      <FormInput testID="production-performances-new-symbol" value={newSymbol} onChangeText={setNewSymbol} style={styles.input} />

      <ThemedText type="small" themeColor="textSecondary">
        備考（任意）
      </ThemedText>
      <FormInput kind="textarea" testID="production-performances-new-remarks" value={newRemarks} onChangeText={setNewRemarks} style={[styles.input, styles.inputLong]} />

      {errorMessage && (
        <ThemedText testID="production-performances-error" style={styles.error}>
          {errorMessage}
        </ThemedText>
      )}

      <TouchableOpacity
        testID="production-performances-create"
        onPress={handleCreate}
        disabled={createPerformance.isPending || !newDate.trim() || !newStartTime.trim()}
        style={[styles.button, createPerformance.isPending && styles.buttonDisabled]}
      >
        {createPerformance.isPending ? <ActivityIndicator color="#fff" /> : <ThemedText style={styles.buttonText}>＋ 公演スケジュールを追加</ThemedText>}
      </TouchableOpacity>
    </>
  );
}

/**
 * Phase 6: form state lives locally here (initialized once from
 * `initialEdit`), matching this codebase's own established pattern for
 * every other inline edit form (production `edit.tsx`, Rehearsal
 * attendance remarks) - not lifted to the list-level parent, which only
 * needs to know WHICH row is open.
 */
function PerformanceEditRow({
  performanceId,
  initialEdit,
  onCancelEdit,
  onSaved,
  onError,
}: {
  performanceId: string;
  initialEdit: EditState;
  onCancelEdit: () => void;
  onSaved: () => void;
  onError: (message: string) => void;
}) {
  const [edit, setEdit] = useState(initialEdit);
  const updatePerformance = useUpdatePerformance(performanceId);

  async function handleSave() {
    try {
      await updatePerformance.mutateAsync({
        performanceDate: edit.performanceDate.trim(),
        startTime: edit.startTime.trim(),
        endTime: edit.endTime.trim() || null,
        capacity: Number(edit.capacity.trim()),
        remarks: edit.remarks.trim() || null,
        symbol: edit.symbol.trim() || null,
        status: edit.status,
      });
      onSaved();
    } catch (error) {
      if (error instanceof ApiError && error.code === 'stageart_performance_duplicate_datetime') {
        onError('同じ公演日・開演時刻の公演スケジュールは登録できません。');
      } else {
        onError(getErrorMessage(error));
      }
    }
  }

  return (
    <View style={styles.editRow} testID={`performance-edit-row-${performanceId}`}>
      <FormInput
        kind="date"
        testID={`performance-edit-date-${performanceId}`}
        value={edit.performanceDate}
        onChangeText={(value) => setEdit({ ...edit, performanceDate: value })}
        style={styles.editInput}
      />
      <FormInput
        testID={`performance-edit-start-time-${performanceId}`}
        value={edit.startTime}
        onChangeText={(value) => setEdit({ ...edit, startTime: value })}
        style={styles.editInput}
      />
      <FormInput
        testID={`performance-edit-end-time-${performanceId}`}
        value={edit.endTime}
        onChangeText={(value) => setEdit({ ...edit, endTime: value })}
        style={styles.editInput}
      />
      <FormInput
        testID={`performance-edit-capacity-${performanceId}`}
        value={edit.capacity}
        onChangeText={(value) => setEdit({ ...edit, capacity: value })}
        keyboardType="number-pad"
        style={styles.editInput}
      />
      <View style={styles.statusOptions}>
        {EDITABLE_STATUS_OPTIONS.map((option) => (
          <TouchableOpacity
            key={option}
            testID={`performance-edit-status-${option}-${performanceId}`}
            onPress={() => setEdit({ ...edit, status: option })}
            style={[styles.statusOption, edit.status === option && styles.statusOptionSelected]}
            accessibilityState={{ selected: edit.status === option }}
          >
            <ThemedText type={edit.status === option ? 'smallBold' : 'small'}>{STATUS_LABEL[option]}</ThemedText>
          </TouchableOpacity>
        ))}
      </View>
      <View style={styles.actionButtons}>
        <TouchableOpacity testID={`performance-edit-save-${performanceId}`} onPress={handleSave} disabled={updatePerformance.isPending}>
          <ThemedText type="link">保存</ThemedText>
        </TouchableOpacity>
        <TouchableOpacity testID={`performance-edit-cancel-${performanceId}`} onPress={onCancelEdit}>
          <ThemedText type="link">キャンセル</ThemedText>
        </TouchableOpacity>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  pageContainer: { padding: Spacing.five, paddingBottom: Spacing.six },
  pageTitle: { marginBottom: Spacing.four, fontSize: 32, lineHeight: 40 },
  sectionTitle: { marginTop: Spacing.five, marginBottom: Spacing.two },
  list: { gap: Spacing.one, width: '100%', maxWidth: 1100 },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: Spacing.two,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: '#eee',
    gap: Spacing.two,
  },
  editRow: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: Spacing.two,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: '#eee',
    gap: Spacing.two,
  },
  colDate: { width: 110 },
  colTime: { width: 70 },
  colCapacity: { width: 60 },
  colStatus: { width: 80 },
  statusOptions: { flexDirection: 'row', gap: Spacing.one },
  statusOption: {
    paddingVertical: Spacing.one,
    paddingHorizontal: Spacing.two,
    borderRadius: Radius.medium,
    borderWidth: 1,
    borderColor: '#ccc',
  },
  statusOptionSelected: { borderColor: BrandColors.warmAmber, backgroundColor: BrandColors.warmAmber + '22' },
  actionButtons: { flexDirection: 'row', gap: Spacing.two },
  editInput: {
    width: 90,
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    paddingHorizontal: Spacing.two,
    paddingVertical: Spacing.one,
  },
  input: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    paddingHorizontal: Spacing.three,
    paddingVertical: Spacing.two,
    fontSize: 16,
    marginBottom: Spacing.two,
  },
  error: { color: '#a6483a', marginTop: Spacing.two },
  button: {
    backgroundColor: BrandColors.warmAmber,
    borderRadius: Radius.medium,
    paddingVertical: Spacing.two,
    paddingHorizontal: Spacing.four,
    alignItems: 'center',
    marginTop: Spacing.three,
    alignSelf: 'flex-start',
  },
  buttonDisabled: { opacity: 0.6 },
  buttonText: { color: '#fff', fontWeight: '600' },
});
