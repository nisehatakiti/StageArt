import { useLocalSearchParams } from 'expo-router';
import Head from 'expo-router/head';
import { useState } from 'react';
import { ActivityIndicator, ScrollView, StyleSheet, TextInput, TouchableOpacity } from 'react-native';

import { ApiError } from '@/api/errors';
import { AppShell } from '@/components/app-shell';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import { usePublicQuestionnaire, useSubmitQuestionnaireResponse } from '@/features/questionnaire/useQuestionnaire';
import type { PublicQuestionnaire, QuestionnaireAnswerInput } from '@/types/api';
import { getErrorMessage } from '@/utils/errorMessage';

type AnswerValue = string | number | string[];

/**
 * アンケート実装指示書 §5/§14/§15/§16/§17: the anonymous public answer
 * page - `/{organization-slug}/{production-slug}/questionnaire`, the
 * exact same URL for every respondent regardless of whether they arrived
 * from the invite Email, a QR code, or typed it directly (§12). No
 * authentication, no StageArt account, no Reservation number, no email
 * input anywhere on this screen - the request body sent to
 * `POST /questionnaires/by-slug/{slug}/responses` never carries anything
 * beyond `answers` (see useSubmitQuestionnaireResponse/api.ts). Required-
 * question validation happens here for a responsive UI, but the Backend
 * re-validates everything server-side (§16) - a 422 is shown as a plain
 * error message, not silently swallowed.
 */
export default function PublicQuestionnaireScreen() {
  const { productionSlug } = useLocalSearchParams<{ organizationSlug: string; productionSlug: string }>();
  const query = usePublicQuestionnaire(productionSlug);
  const submitResponse = useSubmitQuestionnaireResponse(productionSlug);

  const [answers, setAnswers] = useState<Record<string, AnswerValue>>({});
  const [submitted, setSubmitted] = useState(false);
  const [validationError, setValidationError] = useState<string | null>(null);

  function setAnswer(questionId: string, value: AnswerValue) {
    setAnswers((previous) => ({ ...previous, [questionId]: value }));
  }

  function toggleMultipleChoice(questionId: string, choiceId: string) {
    setAnswers((previous) => {
      const current = Array.isArray(previous[questionId]) ? (previous[questionId] as string[]) : [];
      const next = current.includes(choiceId) ? current.filter((id) => id !== choiceId) : [...current, choiceId];
      return { ...previous, [questionId]: next };
    });
  }

  function isBlank(value: AnswerValue | undefined): boolean {
    return value === undefined || value === '' || (Array.isArray(value) && value.length === 0);
  }

  async function handleSubmit(questionnaire: PublicQuestionnaire) {
    setValidationError(null);

    const missingRequired = questionnaire.questions.some((question) => question.required && isBlank(answers[question.id]));

    if (missingRequired) {
      setValidationError('必須の質問にすべて回答してください。');
      return;
    }

    const payload: QuestionnaireAnswerInput[] = questionnaire.questions
      .filter((question) => !isBlank(answers[question.id]))
      .map((question) => ({ question_id: question.id, value: answers[question.id] }));

    try {
      await submitResponse.mutateAsync(payload);
      setSubmitted(true);
    } catch (error) {
      setValidationError(getErrorMessage(error));
    }
  }

  return (
    <AppShell scroll>
      {query.data && (
        <Head>
          <title>{query.data.title} | {query.data.production_name} | StageArt</title>
          <meta name="description" content={`${query.data.production_name}「${query.data.title}」アンケート`} />
        </Head>
      )}
      <ScrollView contentContainerStyle={styles.container}>
        {query.isLoading && <ActivityIndicator testID="public-questionnaire-loading" />}

        {query.isError && (
          <ThemedText testID="public-questionnaire-not-found">
            {query.error instanceof ApiError && query.error.statusCode === 404
              ? 'このアンケートは見つかりませんでした。'
              : getErrorMessage(query.error)}
          </ThemedText>
        )}

        {query.data && !submitted && (
          <ThemedView testID="public-questionnaire-content" style={styles.content}>
            <ThemedText type="small" themeColor="textSecondary" testID="public-questionnaire-production-name">
              {query.data.production_name}
            </ThemedText>
            <ThemedText type="title" testID="public-questionnaire-title">
              {query.data.title}
            </ThemedText>
            {query.data.description && (
              <ThemedText themeColor="textSecondary" testID="public-questionnaire-description">
                {query.data.description}
              </ThemedText>
            )}

            {!query.data.accepting_responses ? (
              <ThemedText testID="public-questionnaire-not-accepting" style={styles.notice}>
                {query.data.status === 'CLOSED'
                  ? 'このアンケートは回答受付を終了しました。'
                  : '現在このアンケートは回答を受け付けていません。'}
              </ThemedText>
            ) : (
              <ThemedView style={styles.form}>
                {query.data.questions.map((question) => (
                  <ThemedView key={question.id} style={styles.questionBlock} testID={`public-question-${question.id}`}>
                    <ThemedText type="smallBold">
                      {question.text}
                      {question.required && <ThemedText style={styles.required}> ＊必須</ThemedText>}
                    </ThemedText>

                    {question.type === 'SINGLE_CHOICE' && (
                      <ThemedView style={styles.choiceList}>
                        {question.choices.map((choice) => {
                          const selected = answers[question.id] === choice.id;
                          return (
                            <TouchableOpacity
                              key={choice.id}
                              testID={`public-question-${question.id}-choice-${choice.id}`}
                              onPress={() => setAnswer(question.id, choice.id)}
                              style={[styles.choiceRow, selected && styles.choiceRowSelected]}
                              accessibilityRole="radio"
                              accessibilityState={{ selected }}
                            >
                              <ThemedText>{selected ? '◉' : '○'} {choice.label}</ThemedText>
                            </TouchableOpacity>
                          );
                        })}
                      </ThemedView>
                    )}

                    {question.type === 'MULTIPLE_CHOICE' && (
                      <ThemedView style={styles.choiceList}>
                        {question.choices.map((choice) => {
                          const selected = Array.isArray(answers[question.id]) && (answers[question.id] as string[]).includes(choice.id);
                          return (
                            <TouchableOpacity
                              key={choice.id}
                              testID={`public-question-${question.id}-choice-${choice.id}`}
                              onPress={() => toggleMultipleChoice(question.id, choice.id)}
                              style={[styles.choiceRow, selected && styles.choiceRowSelected]}
                              accessibilityRole="checkbox"
                              accessibilityState={{ checked: selected }}
                            >
                              <ThemedText>{selected ? '☑' : '☐'} {choice.label}</ThemedText>
                            </TouchableOpacity>
                          );
                        })}
                      </ThemedView>
                    )}

                    {question.type === 'RATING_5' && (
                      <ThemedView style={styles.ratingRow}>
                        {[1, 2, 3, 4, 5].map((value) => {
                          const selected = answers[question.id] === value;
                          return (
                            <TouchableOpacity
                              key={value}
                              testID={`public-question-${question.id}-rating-${value}`}
                              onPress={() => setAnswer(question.id, value)}
                              style={[styles.ratingButton, selected && styles.ratingButtonSelected]}
                            >
                              <ThemedText style={selected ? styles.ratingTextSelected : undefined}>{value}</ThemedText>
                            </TouchableOpacity>
                          );
                        })}
                      </ThemedView>
                    )}

                    {question.type === 'FREE_TEXT' && (
                      <TextInput
                        testID={`public-question-${question.id}-text`}
                        value={typeof answers[question.id] === 'string' ? (answers[question.id] as string) : ''}
                        onChangeText={(value) => setAnswer(question.id, value)}
                        multiline
                        style={styles.textInput}
                      />
                    )}

                    {question.type === 'YES_NO' && (
                      <ThemedView style={styles.yesNoRow}>
                        <TouchableOpacity
                          testID={`public-question-${question.id}-yes`}
                          onPress={() => setAnswer(question.id, 'YES')}
                          style={[styles.yesNoButton, answers[question.id] === 'YES' && styles.yesNoButtonSelected]}
                        >
                          <ThemedText style={answers[question.id] === 'YES' ? styles.yesNoTextSelected : undefined}>はい</ThemedText>
                        </TouchableOpacity>
                        <TouchableOpacity
                          testID={`public-question-${question.id}-no`}
                          onPress={() => setAnswer(question.id, 'NO')}
                          style={[styles.yesNoButton, answers[question.id] === 'NO' && styles.yesNoButtonSelected]}
                        >
                          <ThemedText style={answers[question.id] === 'NO' ? styles.yesNoTextSelected : undefined}>いいえ</ThemedText>
                        </TouchableOpacity>
                      </ThemedView>
                    )}
                  </ThemedView>
                ))}

                {validationError && (
                  <ThemedText testID="public-questionnaire-error" style={styles.error}>
                    {validationError}
                  </ThemedText>
                )}

                <TouchableOpacity
                  testID="public-questionnaire-submit"
                  onPress={() => handleSubmit(query.data)}
                  disabled={submitResponse.isPending}
                  style={[styles.submitButton, submitResponse.isPending && styles.submitButtonDisabled]}
                >
                  {submitResponse.isPending ? <ActivityIndicator color="#fff" /> : <ThemedText style={styles.submitButtonText}>回答を送信する</ThemedText>}
                </TouchableOpacity>
              </ThemedView>
            )}
          </ThemedView>
        )}

        {submitted && (
          <ThemedView testID="public-questionnaire-thanks" style={styles.content}>
            <ThemedText type="title">ご回答ありがとうございました。</ThemedText>
          </ThemedView>
        )}
      </ScrollView>
    </AppShell>
  );
}

const styles = StyleSheet.create({
  container: { padding: Spacing.four },
  content: { gap: Spacing.two },
  notice: { marginTop: Spacing.three },
  form: { gap: Spacing.four, marginTop: Spacing.three },
  questionBlock: { gap: Spacing.two },
  required: { color: '#a6483a' },
  choiceList: { gap: Spacing.one },
  choiceRow: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: Radius.medium,
    paddingVertical: Spacing.two,
    paddingHorizontal: Spacing.three,
  },
  choiceRowSelected: { borderColor: BrandColors.warmAmber, backgroundColor: '#FBEFDD' },
  ratingRow: { flexDirection: 'row', gap: Spacing.two },
  ratingButton: {
    width: 44,
    height: 44,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: Radius.medium,
  },
  ratingButtonSelected: { borderColor: BrandColors.warmAmber, backgroundColor: BrandColors.warmAmber },
  ratingTextSelected: { color: '#fff', fontWeight: '600' },
  textInput: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: Radius.medium,
    paddingHorizontal: Spacing.three,
    paddingVertical: Spacing.two,
    minHeight: 96,
    textAlignVertical: 'top',
    fontSize: 16,
  },
  yesNoRow: { flexDirection: 'row', gap: Spacing.two },
  yesNoButton: {
    flex: 1,
    alignItems: 'center',
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: Radius.medium,
    paddingVertical: Spacing.two,
  },
  yesNoButtonSelected: { borderColor: BrandColors.warmAmber, backgroundColor: BrandColors.warmAmber },
  yesNoTextSelected: { color: '#fff', fontWeight: '600' },
  error: { color: '#a6483a' },
  submitButton: {
    backgroundColor: BrandColors.warmAmber,
    borderRadius: Radius.medium,
    paddingVertical: Spacing.two,
    paddingHorizontal: Spacing.four,
    alignItems: 'center',
    alignSelf: 'flex-start',
  },
  submitButtonDisabled: { opacity: 0.6 },
  submitButtonText: { color: '#fff', fontWeight: '600' },
});
