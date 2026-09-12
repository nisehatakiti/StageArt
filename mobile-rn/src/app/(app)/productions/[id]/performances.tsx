import { useLocalSearchParams, type Href } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, StyleSheet, TouchableOpacity, View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { ThemedTextInput } from '@/components/themed-text-input';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import { useCancelPerformance, useCreatePerformance, usePerformances, useUpdatePerformance } from '@/features/performance/usePerformances';
import { useProduction } from '@/features/production/useProductions';
import { useProductionOrganization } from '@/features/production/useProductionOrganization';
import type { Performance } from '@/types/api';
import { getErrorMessage } from '@/utils/errorMessage';

const STATUS_LABEL: Record<string, string> = {
  DRAFT: '下書き',
  PUBLISHED: '公開中',
  SOLD_OUT: '満席',
  FINISHED: '終了',
  CANCELLED: '中止',
};

type EditState = {
  performanceDate: string;
  startTime: string;
  endTime: string;
  capacity: string;
  remarks: string;
  symbol: string;
};

function toEditState(performance: Performance): EditState {
  return {
    performanceDate: performance.performance_date,
    startTime: performance.start_time.slice(0, 5),
    endTime: performance.end_time?.slice(0, 5) ?? '',
    capacity: String(performance.capacity),
    remarks: performance.remarks ?? '',
    symbol: performance.symbol ?? '',
  };
}

/**
 * StageArt Phase 2 Performance基盤 §22/§23/§24: 公演回管理 - Performance
 * list (公演日/開演時刻/終演予定時刻/定員/Status, plus 記号/備考), create
 * form (定員 pre-filled from Production.capacity, per §23), and inline
 * edit. "中止" is the only delete-like action (Status -> CANCELLED); a
 * cancelled Performance stays in the list as history, never physically
 * removed (§24).
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
  const [editState, setEditState] = useState<EditState | null>(null);
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
    { label: '公演回管理' },
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
      setErrorMessage(getErrorMessage(error));
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
          公演回管理はPrimaryManagerまたは公演回管理の権限を持つ担当者のみ利用できます。
        </ThemedText>
      </>
    );
  }

  return (
    <>
      <ThemedText type="title" style={styles.pageTitle}>
        公演回管理
      </ThemedText>

      {performancesQuery.isLoading && <ActivityIndicator testID="production-performances-list-loading" />}
      {performancesQuery.isError && (
        <ThemedText testID="production-performances-list-error">{getErrorMessage(performancesQuery.error)}</ThemedText>
      )}
      {!performancesQuery.isLoading && !performancesQuery.isError && performances.length === 0 && (
        <ThemedText testID="production-performances-empty" themeColor="textSecondary">
          まだ公演回がありません。
        </ThemedText>
      )}

      {performances.length > 0 && (
        <View style={styles.list} testID="production-performances-list">
          {performances.map((performance) =>
            editingId === performance.id && editState ? (
              <PerformanceEditRow
                key={performance.id}
                edit={editState}
                onChange={setEditState}
                onCancelEdit={() => {
                  setEditingId(null);
                  setEditState(null);
                }}
                performanceId={performance.id}
                onSaved={() => {
                  setEditingId(null);
                  setEditState(null);
                }}
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
                    onPress={() => {
                      setEditingId(performance.id);
                      setEditState(toEditState(performance));
                    }}
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
        公演回を追加
      </ThemedText>

      <ThemedText type="small" themeColor="textSecondary">
        公演日
      </ThemedText>
      <ThemedTextInput testID="production-performances-new-date" value={newDate} onChangeText={setNewDate} placeholder="2026-10-10" style={styles.input} />

      <ThemedText type="small" themeColor="textSecondary">
        開演時刻
      </ThemedText>
      <ThemedTextInput
        testID="production-performances-new-start-time"
        value={newStartTime}
        onChangeText={setNewStartTime}
        placeholder="13:00"
        style={styles.input}
      />

      <ThemedText type="small" themeColor="textSecondary">
        終演予定時刻（任意）
      </ThemedText>
      <ThemedTextInput
        testID="production-performances-new-end-time"
        value={newEndTime}
        onChangeText={setNewEndTime}
        placeholder="15:00"
        style={styles.input}
      />

      <ThemedText type="small" themeColor="textSecondary">
        定員
      </ThemedText>
      <ThemedTextInput
        testID="production-performances-new-capacity"
        value={newCapacity}
        onChangeText={setNewCapacity}
        keyboardType="number-pad"
        style={styles.input}
      />

      <ThemedText type="small" themeColor="textSecondary">
        記号（任意）
      </ThemedText>
      <ThemedTextInput testID="production-performances-new-symbol" value={newSymbol} onChangeText={setNewSymbol} style={styles.input} />

      <ThemedText type="small" themeColor="textSecondary">
        備考（任意）
      </ThemedText>
      <ThemedTextInput testID="production-performances-new-remarks" value={newRemarks} onChangeText={setNewRemarks} style={styles.input} />

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
        {createPerformance.isPending ? <ActivityIndicator color="#fff" /> : <ThemedText style={styles.buttonText}>＋ 公演回を追加</ThemedText>}
      </TouchableOpacity>
    </>
  );
}

function PerformanceEditRow({
  performanceId,
  edit,
  onChange,
  onCancelEdit,
  onSaved,
  onError,
}: {
  performanceId: string;
  edit: EditState;
  onChange: (edit: EditState) => void;
  onCancelEdit: () => void;
  onSaved: () => void;
  onError: (message: string) => void;
}) {
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
      });
      onSaved();
    } catch (error) {
      onError(getErrorMessage(error));
    }
  }

  return (
    <View style={styles.editRow} testID={`performance-edit-row-${performanceId}`}>
      <ThemedTextInput
        testID={`performance-edit-date-${performanceId}`}
        value={edit.performanceDate}
        onChangeText={(value) => onChange({ ...edit, performanceDate: value })}
        style={styles.editInput}
      />
      <ThemedTextInput
        testID={`performance-edit-start-time-${performanceId}`}
        value={edit.startTime}
        onChangeText={(value) => onChange({ ...edit, startTime: value })}
        style={styles.editInput}
      />
      <ThemedTextInput
        testID={`performance-edit-end-time-${performanceId}`}
        value={edit.endTime}
        onChangeText={(value) => onChange({ ...edit, endTime: value })}
        style={styles.editInput}
      />
      <ThemedTextInput
        testID={`performance-edit-capacity-${performanceId}`}
        value={edit.capacity}
        onChangeText={(value) => onChange({ ...edit, capacity: value })}
        keyboardType="number-pad"
        style={styles.editInput}
      />
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
  pageTitle: { marginBottom: Spacing.two },
  sectionTitle: { marginTop: Spacing.three, marginBottom: Spacing.one },
  list: { gap: Spacing.one },
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
