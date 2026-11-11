import { useLocalSearchParams, useRouter } from 'expo-router';
import { useRef, useState } from 'react';
import { ActivityIndicator, ScrollView, StyleSheet, TouchableOpacity } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { DateTimeInput } from '@/components/date-time-input';
import { ThemedText } from '@/components/themed-text';
import { ThemedTextInput } from '@/components/themed-text-input';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';
import { rehearsalActivityTypeLabel } from '@/features/attendance/activityType';
import { useRehearsals } from '@/features/attendance/useRehearsals';
import { useProduction } from '@/features/production/useProductions';
import { formatDayHeader, formatTime } from '@/features/schedule/groupByDay';
import { splitJstDateTime } from '@/features/schedule/jst';
import { endDayLabel, rowDateTimes, rowFromItem, type RowOriginal } from '@/features/schedule/timetableRowTimes';
import { useSaveAndPublishTimetable, useTimetableEditorItems } from '@/features/schedule/useTimetableAuthoring';
import { getErrorMessage } from '@/utils/errorMessage';
import type { Rehearsal, TimetableItem } from '@/types/api';

type ItemRow = {
  key: string;
  title: string;
  date: string;
  startTime: string;
  endTime: string;
  venue: string;
  notes: string;
  /** PR #7 R12: the start/end this row was loaded with (none for a new row). */
  original?: RowOriginal;
};

/** "2026-08-20T10:00:00+09:00" -> { date: "2026-08-20", time: "10:00" }.
 * PR #7 R11: the Asia/Tokyo date and time of the value (the same rule the
 * list screens use - features/schedule/jst.ts), not the digits as sent:
 * before R11 the API returned "10:00:00+00:00" for a 10:00 JST item, which
 * this editor showed as 10:00 but the list screens showed as 19:00. Rows
 * are saved back with "+09:00". */
function splitDateTime(value: string | null): { date: string; time: string } {
  return splitJstDateTime(value);
}

function toRow(item: TimetableItem): ItemRow {
  // PR #7 R12: the row's date is the START date; an end on the next day is
  // shown as 「翌日」 and kept (features/schedule/timetableRowTimes.ts).
  const original = rowFromItem(item.start_date_time, item.end_date_time);
  return {
    key: item.id,
    title: item.title,
    date: original.date,
    startTime: original.startTime,
    endTime: original.endTime,
    venue: item.venue ?? '',
    notes: item.notes ?? '',
    original,
  };
}

/**
 * PR #7 R8 (2-5; docs/04-CommonNavigationDesign.md §20.3): 小屋入り～本番 →
 * タイムテーブル →「＋ タイムテーブルを作成」. The timetable is created and
 * edited here, independently of the 予定 (稽古) detail screen, which has no
 * timetable controls.
 *
 * A Timetable belongs to one 予定 (Timetable.md "Rehearsal Relationship";
 * no Production-level Timetable in this version), so the manager first
 * picks the 予定 whose day this timetable describes. 「保存」 stores the
 * items in that 予定's DRAFT Version and publishes it (DRAFT -> PUBLISHED)
 * in the same operation - there is no separate 「内容を公開する」 button.
 * 備考 (notes) is optional. Only the PrimaryManager / REHEARSAL_MANAGER may
 * edit; the Backend enforces this and the screen says so to others.
 */
export default function TimetableEditorScreen() {
  const { id: productionId } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();

  const productionQuery = useProduction(productionId);
  const canManage =
    productionQuery.data?.is_primary_manager === true ||
    productionQuery.data?.delegate_roles?.includes('REHEARSAL_MANAGER') === true;

  const rehearsalsQuery = useRehearsals(productionId);
  const targets = (rehearsalsQuery.data ?? []).filter((rehearsal) => rehearsal.status !== 'CANCELLED');

  const [selectedRehearsalId, setSelectedRehearsalId] = useState<string | null>(null);
  const selectedRehearsal = targets.find((rehearsal) => rehearsal.id === selectedRehearsalId) ?? null;

  const itemsQuery = useTimetableEditorItems(selectedRehearsalId ?? undefined);
  const saveMutation = useSaveAndPublishTimetable(selectedRehearsalId ?? undefined);

  const [rows, setRows] = useState<ItemRow[]>([]);
  const [rowsSeededFrom, setRowsSeededFrom] = useState<string | null>(null);
  const [validationError, setValidationError] = useState<string | null>(null);
  /** Keys for rows added on this screen (not saved yet). */
  const newRowSequence = useRef(0);

  // Seed the editable rows once per loaded result (render-time seeding,
  // as in the other edit screens; not an effect).
  const seedKey = selectedRehearsalId && itemsQuery.data ? `${selectedRehearsalId}:${itemsQuery.dataUpdatedAt}` : null;
  if (seedKey && itemsQuery.data && seedKey !== rowsSeededFrom && !saveMutation.isPending) {
    setRows(itemsQuery.data.items.map(toRow));
    setRowsSeededFrom(seedKey);
  }

  function selectRehearsal(rehearsalId: string) {
    setSelectedRehearsalId(rehearsalId);
    setRows([]);
    setRowsSeededFrom(null);
    setValidationError(null);
    saveMutation.reset();
  }

  function updateRow(key: string, patch: Partial<ItemRow>) {
    setRows((current) => current.map((row) => (row.key === key ? { ...row, ...patch } : row)));
  }

  function addRow() {
    newRowSequence.current += 1;
    const key = `new-${newRowSequence.current}`;
    const defaultDate = splitDateTime(selectedRehearsal?.start_date_time ?? null).date;
    setRows((current) => [
      ...current,
      { key, title: '', date: defaultDate, startTime: '', endTime: '', venue: '', notes: '' },
    ]);
  }

  function removeRow(key: string) {
    setRows((current) => current.filter((row) => row.key !== key));
  }

  function handleSave() {
    if (rows.length === 0) {
      setValidationError('項目を1つ以上追加してください。');
      return;
    }
    const incomplete = rows.findIndex((row) => !row.title.trim() || !row.date || !row.startTime);
    if (incomplete >= 0) {
      setValidationError(`${incomplete + 1}行目：項目名・日付・開始時刻を入力してください。`);
      return;
    }
    const times = rows.map((row) => rowDateTimes(row, row.original));
    const sameTime = times.findIndex((t) => !t.ok);
    if (sameTime >= 0) {
      setValidationError(`${sameTime + 1}行目：開始と終了が同じ時刻です。終了時刻を変えるか、空欄にしてください。`);
      return;
    }
    setValidationError(null);

    saveMutation.mutate(
      rows.map((row, index) => {
        const t = times[index] as { ok: true; start: string; end: string | null };
        return {
          title: row.title.trim(),
          startDateTime: t.start,
          endDateTime: t.end,
          venue: row.venue.trim() || null,
          notes: row.notes.trim() || null,
          displayOrder: index + 1,
        };
      })
    );
  }

  return (
    <SafeAreaView style={styles.safeArea}>
      <TouchableOpacity onPress={() => router.back()} testID="timetable-editor-back" style={styles.backRow}>
        <ThemedText type="link">← タイムテーブル</ThemedText>
      </TouchableOpacity>
      <ScrollView contentContainerStyle={styles.content} testID="timetable-editor">
        <ThemedText type="subtitle">タイムテーブルを作成・編集</ThemedText>

        {productionQuery.isLoading && <ActivityIndicator testID="timetable-editor-loading" />}

        {productionQuery.data && !canManage && (
          <ThemedText testID="timetable-editor-forbidden">
            タイムテーブルを作成・編集できるのは、公演管理者または稽古管理代理人です。
          </ThemedText>
        )}

        {canManage && (
          <>
            <ThemedView style={styles.card}>
              <ThemedText type="smallBold">対象の予定</ThemedText>
              {rehearsalsQuery.isLoading && <ActivityIndicator />}
              {rehearsalsQuery.isError && (
                <ThemedText testID="timetable-targets-error">{getErrorMessage(rehearsalsQuery.error)}</ThemedText>
              )}
              {!rehearsalsQuery.isLoading && !rehearsalsQuery.isError && targets.length === 0 && (
                <ThemedText type="small" themeColor="textSecondary" testID="timetable-targets-empty">
                  予定がありません。先に予定管理で予定を作成してください。
                </ThemedText>
              )}
              {targets.map((rehearsal) => (
                <TargetRow
                  key={rehearsal.id}
                  rehearsal={rehearsal}
                  selected={rehearsal.id === selectedRehearsalId}
                  onPress={() => selectRehearsal(rehearsal.id)}
                />
              ))}
            </ThemedView>

            {selectedRehearsal && (
              <ThemedView style={styles.card} testID="timetable-items-editor">
                <ThemedText type="smallBold">項目</ThemedText>
                {itemsQuery.isLoading && <ActivityIndicator testID="timetable-items-loading" />}
                {itemsQuery.isError && (
                  <ThemedText testID="timetable-items-error">{getErrorMessage(itemsQuery.error)}</ThemedText>
                )}
                {itemsQuery.data?.hasDraft && (
                  <ThemedText type="small" themeColor="textSecondary" testID="timetable-draft-notice">
                    保存されていない下書きがあります。保存するとメンバーに公開されます。
                  </ThemedText>
                )}
                {rows.map((row, index) => (
                  <ThemedView key={row.key} style={styles.itemRow} testID={`timetable-item-row-${index}`}>
                    <ThemedTextInput
                      testID={`timetable-item-title-${index}`}
                      placeholder="項目名（例：小屋入り、場当たり）"
                      value={row.title}
                      onChangeText={(title) => updateRow(row.key, { title })}
                      accessibilityLabel={`${index + 1}行目の項目名`}
                      style={styles.input}
                    />
                    <ThemedView style={styles.inlineFields}>
                      <DateTimeInput
                        mode="date"
                        testID={`timetable-item-date-${index}`}
                        value={row.date}
                        onChangeText={(date) => updateRow(row.key, { date })}
                        placeholder="日付"
                      />
                      <DateTimeInput
                        mode="time"
                        testID={`timetable-item-start-${index}`}
                        value={row.startTime}
                        onChangeText={(startTime) => updateRow(row.key, { startTime })}
                        placeholder="開始"
                      />
                      <DateTimeInput
                        mode="time"
                        testID={`timetable-item-end-${index}`}
                        value={row.endTime}
                        onChangeText={(endTime) => updateRow(row.key, { endTime })}
                        placeholder="終了（任意）"
                      />
                      {endDayLabel(row, row.original) !== '' && (
                        <ThemedText type="small" testID={`timetable-item-end-day-${index}`} style={styles.endDay}>
                          {endDayLabel(row, row.original)}
                        </ThemedText>
                      )}
                    </ThemedView>
                    <ThemedTextInput
                      testID={`timetable-item-venue-${index}`}
                      placeholder="場所（任意）"
                      value={row.venue}
                      onChangeText={(venue) => updateRow(row.key, { venue })}
                      accessibilityLabel={`${index + 1}行目の場所`}
                      style={styles.input}
                    />
                    <ThemedTextInput
                      testID={`timetable-item-notes-${index}`}
                      placeholder="備考（任意）"
                      value={row.notes}
                      onChangeText={(notes) => updateRow(row.key, { notes })}
                      accessibilityLabel={`${index + 1}行目の備考`}
                      multiline
                      style={[styles.input, styles.notesInput]}
                    />
                    <TouchableOpacity
                      testID={`timetable-item-remove-${index}`}
                      onPress={() => removeRow(row.key)}
                      accessibilityRole="button"
                      accessibilityLabel={`${index + 1}行目を削除`}
                    >
                      <ThemedText type="link">この項目を削除</ThemedText>
                    </TouchableOpacity>
                  </ThemedView>
                ))}
                {!itemsQuery.isLoading && !itemsQuery.isError && (
                  <TouchableOpacity
                    testID="timetable-item-add"
                    onPress={addRow}
                    accessibilityRole="button"
                    accessibilityLabel="項目を追加"
                  >
                    <ThemedText type="link">＋ 項目を追加</ThemedText>
                  </TouchableOpacity>
                )}
              </ThemedView>
            )}

            {selectedRehearsal && !itemsQuery.isLoading && !itemsQuery.isError && (
              <ThemedView style={styles.saveArea}>
                <TouchableOpacity
                  testID="timetable-save"
                  onPress={handleSave}
                  disabled={saveMutation.isPending}
                  accessibilityRole="button"
                  accessibilityLabel="タイムテーブルを保存する"
                  style={[styles.saveButton, saveMutation.isPending && styles.disabledButton]}
                >
                  {saveMutation.isPending ? <ActivityIndicator color="#fff" /> : <ThemedText style={styles.saveButtonText}>保存</ThemedText>}
                </TouchableOpacity>
                <ThemedText type="small" themeColor="textSecondary">
                  保存すると、メンバーのタイムテーブルに反映されます。
                </ThemedText>
                {validationError && <ThemedText testID="timetable-save-validation">{validationError}</ThemedText>}
                {saveMutation.isError && (
                  <ThemedText testID="timetable-save-error">{getErrorMessage(saveMutation.error)}</ThemedText>
                )}
                {saveMutation.isSuccess && (
                  <ThemedText testID="timetable-save-success" themeColor="textSecondary">
                    保存しました。
                  </ThemedText>
                )}
              </ThemedView>
            )}
          </>
        )}
      </ScrollView>
    </SafeAreaView>
  );
}

function TargetRow({ rehearsal, selected, onPress }: { rehearsal: Rehearsal; selected: boolean; onPress: () => void }) {
  const date = rehearsal.start_date_time ? formatDayHeader(new Date(rehearsal.start_date_time)) : '日付未設定';
  const time = rehearsal.start_date_time
    ? `${formatTime(rehearsal.start_date_time)}${rehearsal.end_date_time ? `〜${formatTime(rehearsal.end_date_time)}` : ''}`
    : '';

  return (
    <TouchableOpacity
      testID={`timetable-target-${rehearsal.id}`}
      onPress={onPress}
      accessibilityRole="button"
      accessibilityState={{ selected }}
      accessibilityLabel={`${date} ${time} ${rehearsalActivityTypeLabel(rehearsal.activity_type)}`}
      style={[styles.targetRow, selected && styles.targetRowSelected]}
    >
      <ThemedText type="smallBold">
        {date} {time}
      </ThemedText>
      <ThemedText type="small" themeColor="textSecondary">
        {rehearsalActivityTypeLabel(rehearsal.activity_type)}
        {rehearsal.location ? `・${rehearsal.location}` : ''}
      </ThemedText>
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  safeArea: { flex: 1 },
  backRow: { paddingHorizontal: Spacing.four, paddingVertical: Spacing.two },
  content: { padding: Spacing.four, gap: Spacing.three },
  card: {
    borderWidth: 1,
    borderColor: '#e1dee6',
    borderRadius: 10,
    padding: Spacing.three,
    gap: Spacing.two,
  },
  targetRow: {
    borderWidth: 1,
    borderColor: '#e1dee6',
    borderRadius: 8,
    padding: Spacing.two,
    gap: Spacing.half,
  },
  targetRowSelected: { borderColor: '#4a3f7a', backgroundColor: '#ece8f5' },
  itemRow: {
    borderTopWidth: 1,
    borderTopColor: '#e1dee6',
    paddingTop: Spacing.two,
    gap: Spacing.one,
  },
  inlineFields: { flexDirection: 'row', flexWrap: 'wrap', gap: Spacing.one },
  endDay: { alignSelf: 'center' },
  input: {
    borderWidth: 1,
    borderColor: '#b7b2c0',
    borderRadius: 8,
    paddingHorizontal: Spacing.three,
    paddingVertical: Spacing.two,
    backgroundColor: '#fff',
  },
  notesInput: { minHeight: 60, textAlignVertical: 'top' },
  saveArea: { gap: Spacing.one },
  saveButton: {
    minHeight: 44,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#2e7d32',
  },
  saveButtonText: { color: '#fff', fontWeight: '700', fontSize: 16 },
  disabledButton: { opacity: 0.5 },
});
