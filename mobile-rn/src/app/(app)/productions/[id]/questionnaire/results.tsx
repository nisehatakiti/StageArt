import { useLocalSearchParams } from 'expo-router';
import { ActivityIndicator, StyleSheet, View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Radius, Spacing } from '@/constants/theme';
import { useQuestionnaire, useQuestionnaireResults } from '@/features/questionnaire/useQuestionnaire';
import { useProduction } from '@/features/production/useProductions';
import type { QuestionAggregateResult } from '@/types/api';
import { getErrorMessage } from '@/utils/errorMessage';

/**
 * アンケート実装指示書 §37-§39: 匿名集計結果の表示。回答者を特定できる情報
 * （Person/Reservation/Booker/Email/Ticket/Check-in、"回答者N"のような内部
 * 識別子含む）は一切表示しない - GetQuestionnaireResultsUseCaseが返す値
 * （質問ごとの集計のみ）をそのまま描画する。
 */
export default function ProductionQuestionnaireResultsScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const productionQuery = useProduction(id);
  const production = productionQuery.data;
  const questionnaireQuery = useQuestionnaire(id);
  const questionnaireExists = !!questionnaireQuery.data;
  const resultsQuery = useQuestionnaireResults(id, questionnaireExists);

  const isPrimaryManager = !!production?.is_primary_manager;
  const canManage = isPrimaryManager || !!production?.delegate_roles?.includes('QUESTIONNAIRE_MANAGER');

  if (productionQuery.isLoading || questionnaireQuery.isLoading) {
    return (
      <>
        <ActivityIndicator testID="questionnaire-results-loading" />
      </>
    );
  }

  if (!production) {
    return (
      <>
        <ThemedText testID="questionnaire-results-production-not-found">この公演が見つかりません。</ThemedText>
      </>
    );
  }

  if (!canManage) {
    return (
      <>
        <ThemedText testID="questionnaire-results-forbidden">
          アンケート管理はPrimaryManagerまたはアンケート管理の権限を持つ担当者のみ利用できます。
        </ThemedText>
      </>
    );
  }

  if (!questionnaireExists) {
    return (
      <>
        <ThemedText testID="questionnaire-results-no-questionnaire">この公演にはまだアンケートがありません。</ThemedText>
      </>
    );
  }

  return (
    <>
      <ThemedText type="title" style={styles.pageTitle}>
        回答結果
      </ThemedText>

      {resultsQuery.isLoading && <ActivityIndicator testID="questionnaire-results-list-loading" />}
      {resultsQuery.isError && <ThemedText testID="questionnaire-results-list-error">{getErrorMessage(resultsQuery.error)}</ThemedText>}

      {resultsQuery.data && (
        <>
          <ThemedText type="smallBold" testID="questionnaire-results-total" style={styles.total}>
            総回答数: {resultsQuery.data.total_responses}
          </ThemedText>

          {resultsQuery.data.questions.map((question) => (
            <ThemedView key={question.question_id} style={styles.questionBlock} testID={`questionnaire-result-${question.question_id}`}>
              <ThemedText type="smallBold">{question.text}</ThemedText>
              <QuestionResultBody question={question} />
            </ThemedView>
          ))}
        </>
      )}
    </>
  );
}

function QuestionResultBody({ question }: { question: QuestionAggregateResult }) {
  if (question.choice_counts) {
    const max = Math.max(1, ...question.choice_counts.map((choice) => choice.count));
    return (
      <View style={styles.barList}>
        {question.choice_counts.map((choice) => (
          <View key={choice.choice_id} style={styles.barRow}>
            <ThemedText type="small" style={styles.barLabel}>
              {choice.label}
            </ThemedText>
            <View style={styles.barTrack}>
              <View style={[styles.barFill, { width: `${(choice.count / max) * 100}%` }]} />
            </View>
            <ThemedText type="small">{choice.count}</ThemedText>
          </View>
        ))}
      </View>
    );
  }

  if (question.rating_counts) {
    const max = Math.max(1, ...Object.values(question.rating_counts));
    return (
      <View style={styles.barList}>
        <ThemedText type="small" themeColor="textSecondary">
          平均: {question.rating_average ?? '-'}
        </ThemedText>
        {[1, 2, 3, 4, 5].map((rating) => (
          <View key={rating} style={styles.barRow}>
            <ThemedText type="small" style={styles.barLabel}>
              {rating}
            </ThemedText>
            <View style={styles.barTrack}>
              <View style={[styles.barFill, { width: `${((question.rating_counts?.[String(rating)] ?? 0) / max) * 100}%` }]} />
            </View>
            <ThemedText type="small">{question.rating_counts?.[String(rating)] ?? 0}</ThemedText>
          </View>
        ))}
      </View>
    );
  }

  if (question.yes_count !== null && question.no_count !== null) {
    return (
      <ThemedText type="small">
        はい: {question.yes_count} / いいえ: {question.no_count}
      </ThemedText>
    );
  }

  if (question.free_text_answers) {
    if (question.free_text_answers.length === 0) {
      return (
        <ThemedText type="small" themeColor="textSecondary">
          回答なし
        </ThemedText>
      );
    }
    return (
      <View style={styles.freeTextList}>
        {question.free_text_answers.map((answer, index) => (
          <ThemedText key={index} type="small" style={styles.freeTextItem}>
            ・{answer}
          </ThemedText>
        ))}
      </View>
    );
  }

  return null;
}

const styles = StyleSheet.create({
  pageTitle: { marginBottom: Spacing.two },
  total: { marginBottom: Spacing.three },
  questionBlock: { gap: Spacing.one, marginBottom: Spacing.four },
  barList: { gap: Spacing.half },
  barRow: { flexDirection: 'row', alignItems: 'center', gap: Spacing.two },
  barLabel: { width: 100 },
  barTrack: { flex: 1, height: 10, borderRadius: Radius.small, backgroundColor: '#eee', overflow: 'hidden' },
  barFill: { height: '100%', backgroundColor: '#C6892B' },
  freeTextList: { gap: Spacing.half },
  freeTextItem: { paddingVertical: Spacing.half },
});
