import { useLocalSearchParams, useRouter } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, ScrollView, StyleSheet, TouchableOpacity } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { ThemedText } from '@/components/themed-text';
import { ThemedTextInput } from '@/components/themed-text-input';
import { ThemedView } from '@/components/themed-view';
import { Radius, Spacing } from '@/constants/theme';
import { useRehearsal, useUpdateRehearsal } from '@/features/attendance/useRehearsals';
import { getErrorMessage } from '@/utils/errorMessage';

/**
 * StageArt Rehearsal機能完成度向上: UpdateRehearsalUseCase (Backend側は
 * 既存、title/description/startDateTime/endDateTime/timezone/locationを
 * 無条件上書きする - Rehearsal.php::updateBasicInfo()) をMobileへ接続する
 * 編集画面。入力パターンはcreate.tsxと同じもの（テキスト入力の
 * 日付/時刻/場所）を再利用し、新しい入力コンポーネントは追加しない。
 *
 * 参加メンバー選択はここに含めない -
 * targetPersonIdsはUpdateRehearsalUseCaseの対象外で、Attendance系
 * UseCase（AddRehearsalAttendanceTargetsUseCase等）の責務のまま。
 *
 * descriptionはこの画面では一切表示・編集しない（create.tsx既存仕様に
 * 合わせ、新しい入力欄を追加しない）。ただしUpdateRehearsalUseCaseは
 * 全フィールドを無条件上書きする実装のため、取得済みRehearsalの
 * description値をそのまま更新requestへ渡し、nullで上書きしない
 * （handleSubmit参照）。
 */
export default function EditRehearsalScreen() {
  const { id: productionId, rehearsalId } = useLocalSearchParams<{ id: string; rehearsalId: string }>();
  const router = useRouter();
  const rehearsalQuery = useRehearsal(rehearsalId);
  const updateRehearsal = useUpdateRehearsal(rehearsalId);

  const [hasInitialized, setHasInitialized] = useState(false);
  const [title, setTitle] = useState('');
  const [date, setDate] = useState('');
  const [time, setTime] = useState('');
  const [endTime, setEndTime] = useState('');
  const [location, setLocation] = useState('');

  // "Adjust state during render, exactly once" - unlike create.tsx's own
  // member-selection seeding (which intentionally re-seeds whenever the
  // Participant list query resolves to a new object, since re-picking
  // defaults on refetch is harmless there), this must seed only the
  // FIRST time real data arrives and never again: a background refetch
  // of useRehearsal() producing a new (even if content-identical) object
  // reference must not silently discard whatever the user has already
  // typed into this form.
  if (rehearsalQuery.data && !hasInitialized) {
    setHasInitialized(true);
    setTitle(rehearsalQuery.data.title ?? '');
    setDate(rehearsalQuery.data.start_date_time ? rehearsalQuery.data.start_date_time.slice(0, 10) : '');
    setTime(rehearsalQuery.data.start_date_time ? rehearsalQuery.data.start_date_time.slice(11, 16) : '');
    setEndTime(rehearsalQuery.data.end_date_time ? rehearsalQuery.data.end_date_time.slice(11, 16) : '');
    setLocation(rehearsalQuery.data.location ?? '');
  }

  async function handleSubmit() {
    if (!rehearsalQuery.data) return;

    const startDateTime = date && time ? `${date}T${time}:00+09:00` : undefined;
    const endDateTime = date && endTime ? `${date}T${endTime}:00+09:00` : undefined;

    await updateRehearsal.mutateAsync({
      title: title.trim(),
      // Never edited on this screen - passed straight through unchanged
      // so UpdateRehearsalUseCase's unconditional-overwrite behavior
      // cannot silently wipe an existing description.
      description: rehearsalQuery.data.description,
      startDateTime,
      endDateTime,
      timezone: 'Asia/Tokyo',
      location: location.trim() || undefined,
    });

    router.replace(`/production/${productionId}/schedule/attendance/${rehearsalId}`);
  }

  return (
    <SafeAreaView style={styles.safeArea}>
      {rehearsalQuery.isLoading && (
        <ThemedView style={styles.centered}>
          <ActivityIndicator testID="rehearsal-edit-loading" />
        </ThemedView>
      )}

      {rehearsalQuery.isError && (
        <ThemedView style={styles.centered}>
          <ThemedText testID="rehearsal-edit-error">{getErrorMessage(rehearsalQuery.error)}</ThemedText>
          <TouchableOpacity onPress={() => rehearsalQuery.refetch()} testID="rehearsal-edit-retry" accessibilityRole="button" accessibilityLabel="再読み込み">
            <ThemedText type="link">再読み込み</ThemedText>
          </TouchableOpacity>
        </ThemedView>
      )}

      {!rehearsalQuery.isLoading && !rehearsalQuery.isError && rehearsalQuery.data && (
        <ScrollView contentContainerStyle={styles.container}>
          <ThemedText type="title" style={styles.title}>
            稽古を編集
          </ThemedText>

          <ThemedText type="small" themeColor="textSecondary">
            稽古名
          </ThemedText>
          <ThemedTextInput testID="rehearsal-edit-title" placeholder="稽古名" value={title} onChangeText={setTitle} style={styles.input} />

          <ThemedText type="small" themeColor="textSecondary">
            日付（YYYY-MM-DD）
          </ThemedText>
          <ThemedTextInput
            testID="rehearsal-edit-date"
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
            testID="rehearsal-edit-time"
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
            testID="rehearsal-edit-end-time"
            placeholder="20:00"
            value={endTime}
            onChangeText={setEndTime}
            autoCapitalize="none"
            style={styles.input}
          />

          <ThemedText type="small" themeColor="textSecondary">
            場所
          </ThemedText>
          <ThemedTextInput
            testID="rehearsal-edit-location"
            placeholder="○○スタジオ"
            value={location}
            onChangeText={setLocation}
            style={styles.input}
          />

          {updateRehearsal.isError && <ThemedText style={styles.error}>{getErrorMessage(updateRehearsal.error)}</ThemedText>}

          <TouchableOpacity
            testID="rehearsal-edit-submit"
            onPress={handleSubmit}
            disabled={!title.trim() || updateRehearsal.isPending}
            style={[styles.button, (!title.trim() || updateRehearsal.isPending) && styles.buttonDisabled]}
          >
            {updateRehearsal.isPending ? <ActivityIndicator color="#fff" /> : <ThemedText style={styles.buttonText}>保存する</ThemedText>}
          </TouchableOpacity>
        </ScrollView>
      )}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safeArea: { flex: 1 },
  centered: { flex: 1, alignItems: 'center', justifyContent: 'center', gap: Spacing.two },
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
});
