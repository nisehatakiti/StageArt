import { useLocalSearchParams, useRouter } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, ScrollView, StyleSheet, TouchableOpacity } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { ThemedText } from '@/components/themed-text';
import { ThemedTextInput } from '@/components/themed-text-input';
import { Radius, Spacing } from '@/constants/theme';
import { useCreateRehearsal } from '@/features/attendance/useRehearsals';
import { getErrorMessage } from '@/utils/errorMessage';

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
  const createRehearsal = useCreateRehearsal(productionId);

  const [title, setTitle] = useState('');
  const [date, setDate] = useState('');
  const [time, setTime] = useState('');
  const [location, setLocation] = useState('');

  async function handleSubmit() {
    const startDateTime = date && time ? `${date}T${time}:00+09:00` : undefined;

    const rehearsal = await createRehearsal.mutateAsync({
      title: title.trim(),
      startDateTime,
      timezone: 'Asia/Tokyo',
      location: location.trim() || undefined,
    });

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
          場所
        </ThemedText>
        <ThemedTextInput testID="rehearsal-create-location" placeholder="○○スタジオ" value={location} onChangeText={setLocation} style={styles.input} />

        {createRehearsal.isError && <ThemedText style={styles.error}>{getErrorMessage(createRehearsal.error)}</ThemedText>}

        <TouchableOpacity
          testID="rehearsal-create-submit"
          onPress={handleSubmit}
          disabled={!title.trim() || createRehearsal.isPending}
          style={[styles.button, (!title.trim() || createRehearsal.isPending) && styles.buttonDisabled]}
        >
          {createRehearsal.isPending ? <ActivityIndicator color="#fff" /> : <ThemedText style={styles.buttonText}>作成する</ThemedText>}
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
});
