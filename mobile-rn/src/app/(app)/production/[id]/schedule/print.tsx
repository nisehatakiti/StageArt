import { useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, StyleSheet, TouchableOpacity } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';
import { PAPER_OPTIONS } from '@/features/printView/paperOptions';
import { usePrintTimetable } from '@/features/printView/usePrintTimetable';
import { getErrorMessage } from '@/utils/errorMessage';

/**
 * Print View (§14-16): fetches the Backend-generated PDF
 * (GET /productions/{id}/timetable/print) and hands it to the OS via
 * expo-sharing / expo-print - this screen never renders or generates a
 * PDF itself. Paper options mirror the Flutter app's existing 4 named
 * choices (A4/A3 × 縦/横).
 */
export default function PrintViewScreen() {
  const { id: productionId } = useLocalSearchParams<{ id: string }>();
  const [selectedKey, setSelectedKey] = useState(PAPER_OPTIONS[0].key);
  const printMutation = usePrintTimetable(productionId);

  const selected = PAPER_OPTIONS.find((option) => option.key === selectedKey) ?? PAPER_OPTIONS[0];

  return (
    <SafeAreaView style={styles.safeArea}>
      <ThemedView style={styles.content}>
        <ThemedText type="title" style={styles.title}>
          印刷
        </ThemedText>
        <ThemedText type="small" themeColor="textSecondary">
          用紙サイズを選択してください。
        </ThemedText>

        <ThemedView style={styles.optionList} testID="paper-option-list">
          {PAPER_OPTIONS.map((option) => (
            <TouchableOpacity
              key={option.key}
              onPress={() => setSelectedKey(option.key)}
              testID={`paper-option-${option.key}`}
              accessibilityRole="radio"
              accessibilityState={{ selected: option.key === selectedKey }}
              style={styles.optionRow}
            >
              <ThemedText type={option.key === selectedKey ? 'smallBold' : 'default'}>
                {`${option.key === selectedKey ? '● ' : '○ '}${option.label}`}
              </ThemedText>
            </TouchableOpacity>
          ))}
        </ThemedView>

        {printMutation.isError && (
          <ThemedText testID="print-error" themeColor="text">
            {printMutation.error instanceof Error && printMutation.error.message === 'この端末では共有機能を利用できません。'
              ? printMutation.error.message
              : getErrorMessage(printMutation.error)}
          </ThemedText>
        )}

        {printMutation.isPending && (
          <ThemedView style={styles.pending}>
            <ActivityIndicator testID="print-loading" />
          </ThemedView>
        )}

        <TouchableOpacity
          onPress={() => printMutation.mutate({ paperSize: selected.paperSize, orientation: selected.orientation, action: 'share' })}
          disabled={printMutation.isPending}
          testID="print-share-button"
          accessibilityRole="button"
          accessibilityLabel="共有"
        >
          <ThemedText type="link">共有</ThemedText>
        </TouchableOpacity>

        <TouchableOpacity
          onPress={() => printMutation.mutate({ paperSize: selected.paperSize, orientation: selected.orientation, action: 'print' })}
          disabled={printMutation.isPending}
          testID="print-print-button"
          accessibilityRole="button"
          accessibilityLabel="印刷"
        >
          <ThemedText type="link">印刷</ThemedText>
        </TouchableOpacity>
      </ThemedView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safeArea: { flex: 1 },
  content: { flex: 1, padding: Spacing.four, gap: Spacing.three },
  title: { fontSize: 24, lineHeight: 30 },
  optionList: { gap: Spacing.two },
  optionRow: { paddingVertical: Spacing.one },
  pending: { alignItems: 'flex-start' },
});
