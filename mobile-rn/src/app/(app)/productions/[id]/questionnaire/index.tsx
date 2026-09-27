import QRCode from 'react-native-qrcode-svg';
import { useLocalSearchParams, useRouter, type Href } from 'expo-router';
import { useEffect, useState } from 'react';
import { ActivityIndicator, StyleSheet, TouchableOpacity, View } from 'react-native';

import { ApiError } from '@/api/errors';
import { ThemedText } from '@/components/themed-text';
import { ThemedTextInput } from '@/components/themed-text-input';
import { ThemedView } from '@/components/themed-view';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import {
  useCloseQuestionnaire,
  useCreateQuestionnaire,
  useDeleteQuestion,
  usePublishQuestionnaire,
  useQuestionnaire,
  useReorderQuestions,
  useUpdateQuestionnaire,
} from '@/features/questionnaire/useQuestionnaire';
import { useProduction } from '@/features/production/useProductions';
import type { Question } from '@/types/api';
import { getErrorMessage } from '@/utils/errorMessage';

const STATUS_LABEL: Record<string, string> = {
  DRAFT: '下書き',
  PUBLISHED: '公開中',
  CLOSED: '終了',
};

const TYPE_LABEL: Record<Question['type'], string> = {
  SINGLE_CHOICE: '単一選択',
  MULTIPLE_CHOICE: '複数選択',
  RATING_5: '5段階評価',
  FREE_TEXT: '自由記述',
  YES_NO: 'Yes / No',
};

/**
 * アンケート実装指示書 §29-§32: Production管理画面のアンケート管理トップ。
 * まだQuestionnaireが無ければ作成フォームを、あれば概要（タイトル/説明/状態/
 * 回答期限の編集、公開/終了、公開URL・QR、質問一覧の並べ替え・編集・削除）を
 * 表示する。回答結果は別画面（results.tsx）。
 */
export default function ProductionQuestionnaireScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();
  const productionQuery = useProduction(id);
  const production = productionQuery.data;
  const questionnaireQuery = useQuestionnaire(id);
  const questionnaire = questionnaireQuery.data;
  const notFound = questionnaireQuery.isError && questionnaireQuery.error instanceof ApiError && questionnaireQuery.error.statusCode === 404;

  const isPrimaryManager = !!production?.is_primary_manager;
  const canManage = isPrimaryManager || !!production?.delegate_roles?.includes('QUESTIONNAIRE_MANAGER');

  const createQuestionnaire = useCreateQuestionnaire(id);
  const updateQuestionnaire = useUpdateQuestionnaire(id);
  const publishQuestionnaire = usePublishQuestionnaire(id);
  const closeQuestionnaire = useCloseQuestionnaire(id);
  const deleteQuestion = useDeleteQuestion(id);
  const reorderQuestions = useReorderQuestions(id);

  const [newTitle, setNewTitle] = useState('');
  const [newDescription, setNewDescription] = useState('');

  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [responseEndAt, setResponseEndAt] = useState('');
  const [initialized, setInitialized] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  useEffect(() => {
    if (questionnaire && !initialized) {
      setTitle(questionnaire.title);
      setDescription(questionnaire.description ?? '');
      setResponseEndAt(questionnaire.response_end_at ?? '');
      setInitialized(true);
    }
  }, [questionnaire, initialized]);

  async function handleCreate() {
    setErrorMessage(null);
    try {
      await createQuestionnaire.mutateAsync({ title: newTitle.trim(), description: newDescription.trim() || null });
    } catch (error) {
      setErrorMessage(getErrorMessage(error));
    }
  }

  async function handleSaveDetails() {
    setErrorMessage(null);
    try {
      await updateQuestionnaire.mutateAsync({
        title: title.trim(),
        description: description.trim() || null,
        responseEndAt: responseEndAt.trim() || null,
      });
    } catch (error) {
      setErrorMessage(getErrorMessage(error));
    }
  }

  async function handlePublish() {
    setErrorMessage(null);
    try {
      await publishQuestionnaire.mutateAsync();
    } catch (error) {
      setErrorMessage(getErrorMessage(error));
    }
  }

  async function handleClose() {
    setErrorMessage(null);
    try {
      await closeQuestionnaire.mutateAsync();
    } catch (error) {
      setErrorMessage(getErrorMessage(error));
    }
  }

  async function handleDeleteQuestion(questionId: string) {
    setErrorMessage(null);
    try {
      await deleteQuestion.mutateAsync(questionId);
    } catch (error) {
      setErrorMessage(getErrorMessage(error));
    }
  }

  async function handleMove(questions: Question[], index: number, direction: -1 | 1) {
    const targetIndex = index + direction;
    if (targetIndex < 0 || targetIndex >= questions.length) {
      return;
    }
    const reordered = [...questions];
    [reordered[index], reordered[targetIndex]] = [reordered[targetIndex], reordered[index]];
    setErrorMessage(null);
    try {
      await reorderQuestions.mutateAsync(reordered.map((question) => question.id));
    } catch (error) {
      setErrorMessage(getErrorMessage(error));
    }
  }

  if (productionQuery.isLoading || questionnaireQuery.isLoading) {
    return (
      <>
        <ActivityIndicator testID="production-questionnaire-loading" />
      </>
    );
  }

  if (!production) {
    return (
      <>
        <ThemedText testID="production-questionnaire-production-not-found">この公演が見つかりません。</ThemedText>
      </>
    );
  }

  if (!canManage) {
    return (
      <>
        <ThemedText testID="production-questionnaire-forbidden">
          アンケート管理はPrimaryManagerまたはアンケート管理の権限を持つ担当者のみ利用できます。
        </ThemedText>
      </>
    );
  }

  if (notFound) {
    return (
      <>
        <ThemedText type="title" style={styles.pageTitle}>
          アンケート
        </ThemedText>
        <ThemedText themeColor="textSecondary" style={styles.caption}>
          この公演にはまだアンケートがありません。
        </ThemedText>

        <ThemedText type="small" themeColor="textSecondary">
          タイトル
        </ThemedText>
        <ThemedTextInput testID="questionnaire-create-title" value={newTitle} onChangeText={setNewTitle} style={styles.input} />

        <ThemedText type="small" themeColor="textSecondary">
          説明（任意）
        </ThemedText>
        <ThemedTextInput
          testID="questionnaire-create-description"
          value={newDescription}
          onChangeText={setNewDescription}
          multiline
          style={[styles.input, styles.multilineInput]}
        />

        {errorMessage && (
          <ThemedText testID="production-questionnaire-error" style={styles.error}>
            {errorMessage}
          </ThemedText>
        )}

        <TouchableOpacity
          testID="questionnaire-create-submit"
          onPress={handleCreate}
          disabled={createQuestionnaire.isPending || !newTitle.trim()}
          style={[styles.button, (createQuestionnaire.isPending || !newTitle.trim()) && styles.buttonDisabled]}
        >
          {createQuestionnaire.isPending ? <ActivityIndicator color="#fff" /> : <ThemedText style={styles.buttonText}>アンケートを作成</ThemedText>}
        </TouchableOpacity>
      </>
    );
  }

  if (questionnaireQuery.isError || !questionnaire) {
    return (
      <>
        <ThemedText testID="production-questionnaire-load-error">{getErrorMessage(questionnaireQuery.error)}</ThemedText>
      </>
    );
  }

  const questions = questionnaire.questions;

  return (
    <>
      <ThemedText type="title" style={styles.pageTitle}>
        アンケート
      </ThemedText>

      <View style={styles.statusRow} testID="questionnaire-status">
        <View style={[styles.pill, statusPillStyle(questionnaire.status)]}>
          <ThemedText type="small" style={styles.pillText}>
            {STATUS_LABEL[questionnaire.status] ?? questionnaire.status}
          </ThemedText>
        </View>
        {questionnaire.status === 'DRAFT' && (
          <TouchableOpacity testID="questionnaire-publish" onPress={handlePublish} disabled={publishQuestionnaire.isPending} style={styles.secondaryButton}>
            <ThemedText type="linkPrimary">公開する</ThemedText>
          </TouchableOpacity>
        )}
        {questionnaire.status === 'PUBLISHED' && (
          <TouchableOpacity testID="questionnaire-close" onPress={handleClose} disabled={closeQuestionnaire.isPending} style={styles.secondaryButton}>
            <ThemedText type="linkPrimary">回答を終了する</ThemedText>
          </TouchableOpacity>
        )}
        <TouchableOpacity testID="questionnaire-view-results" onPress={() => router.push(`/productions/${id}/questionnaire/results` as Href)}>
          <ThemedText type="link">回答結果を見る</ThemedText>
        </TouchableOpacity>
      </View>

      <ThemedText type="small" themeColor="textSecondary">
        タイトル
      </ThemedText>
      <ThemedTextInput testID="questionnaire-title" value={title} onChangeText={setTitle} style={styles.input} />

      <ThemedText type="small" themeColor="textSecondary">
        説明（任意）
      </ThemedText>
      <ThemedTextInput
        testID="questionnaire-description"
        value={description}
        onChangeText={setDescription}
        multiline
        style={[styles.input, styles.multilineInput]}
      />

      <ThemedText type="small" themeColor="textSecondary">
        回答期限（任意・ISO日時。例: 2026-12-31T23:59:59）
      </ThemedText>
      <ThemedTextInput
        testID="questionnaire-response-end-at"
        value={responseEndAt}
        onChangeText={setResponseEndAt}
        placeholder="未設定（無期限）"
        style={styles.input}
      />

      {errorMessage && (
        <ThemedText testID="production-questionnaire-error" style={styles.error}>
          {errorMessage}
        </ThemedText>
      )}

      <TouchableOpacity
        testID="questionnaire-save-details"
        onPress={handleSaveDetails}
        disabled={updateQuestionnaire.isPending || !title.trim()}
        style={[styles.button, (updateQuestionnaire.isPending || !title.trim()) && styles.buttonDisabled]}
      >
        {updateQuestionnaire.isPending ? <ActivityIndicator color="#fff" /> : <ThemedText style={styles.buttonText}>保存する</ThemedText>}
      </TouchableOpacity>

      <ThemedText type="subtitle" style={styles.sectionTitle}>
        公開URL・QR
      </ThemedText>
      <ThemedText testID="questionnaire-public-url" selectable style={styles.publicUrl}>
        {questionnaire.public_url}
      </ThemedText>
      <ThemedView style={styles.qrBox} testID="questionnaire-qr">
        <QRCode value={questionnaire.public_url} size={160} />
      </ThemedView>
      <ThemedText type="small" themeColor="textSecondary" style={styles.caption}>
        QRコードとメールに記載されるURLは常に同一で、回答者を識別する情報は含まれません。印刷する場合はこの画面のスクリーンショット・印刷機能をご利用ください。
      </ThemedText>

      <ThemedText type="subtitle" style={styles.sectionTitle}>
        質問一覧
      </ThemedText>

      {questions.length === 0 && (
        <ThemedText testID="questionnaire-questions-empty" themeColor="textSecondary">
          まだ質問がありません。
        </ThemedText>
      )}

      {questions.length > 0 && (
        <View style={styles.list} testID="questionnaire-questions-list">
          {questions.map((question, index) => (
            <View key={question.id} style={styles.questionRow} testID={`questionnaire-question-${question.id}`}>
              <View style={styles.questionInfo}>
                <ThemedText type="smallBold">
                  {index + 1}. {question.text}
                  {question.required && <ThemedText style={styles.required}> ＊必須</ThemedText>}
                </ThemedText>
                <ThemedText type="small" themeColor="textSecondary">
                  {TYPE_LABEL[question.type]}
                  {question.choices.length > 0 ? `（${question.choices.map((choice) => choice.label).join(' / ')}）` : ''}
                </ThemedText>
              </View>
              <View style={styles.questionActions}>
                <TouchableOpacity
                  testID={`questionnaire-question-up-${question.id}`}
                  onPress={() => handleMove(questions, index, -1)}
                  disabled={index === 0 || reorderQuestions.isPending}
                >
                  <ThemedText type="link">↑</ThemedText>
                </TouchableOpacity>
                <TouchableOpacity
                  testID={`questionnaire-question-down-${question.id}`}
                  onPress={() => handleMove(questions, index, 1)}
                  disabled={index === questions.length - 1 || reorderQuestions.isPending}
                >
                  <ThemedText type="link">↓</ThemedText>
                </TouchableOpacity>
                <TouchableOpacity
                  testID={`questionnaire-question-edit-${question.id}`}
                  onPress={() => router.push(`/productions/${id}/questionnaire/question?questionId=${question.id}` as Href)}
                >
                  <ThemedText type="link">編集</ThemedText>
                </TouchableOpacity>
                <TouchableOpacity
                  testID={`questionnaire-question-delete-${question.id}`}
                  onPress={() => handleDeleteQuestion(question.id)}
                  disabled={deleteQuestion.isPending}
                >
                  <ThemedText style={styles.destructiveText}>削除</ThemedText>
                </TouchableOpacity>
              </View>
            </View>
          ))}
        </View>
      )}

      <TouchableOpacity
        testID="questionnaire-add-question"
        onPress={() => router.push(`/productions/${id}/questionnaire/question` as Href)}
        style={styles.button}
      >
        <ThemedText style={styles.buttonText}>＋ 質問を追加</ThemedText>
      </TouchableOpacity>
    </>
  );
}

function statusPillStyle(status: string) {
  if (status === 'PUBLISHED') return styles.pillPublished;
  if (status === 'CLOSED') return styles.pillClosed;
  return styles.pillDraft;
}

const styles = StyleSheet.create({
  pageTitle: { marginBottom: Spacing.two },
  sectionTitle: { marginTop: Spacing.four, marginBottom: Spacing.one },
  caption: { marginBottom: Spacing.two },
  statusRow: { flexDirection: 'row', flexWrap: 'wrap', alignItems: 'center', gap: Spacing.three, marginBottom: Spacing.three },
  pill: { paddingHorizontal: Spacing.two, paddingVertical: Spacing.half, borderRadius: Radius.medium },
  pillDraft: { backgroundColor: '#f7e4de' },
  pillPublished: { backgroundColor: '#e3f3e8' },
  pillClosed: { backgroundColor: '#e5e5e5' },
  pillText: { fontWeight: '600' },
  secondaryButton: {
    borderWidth: 1,
    borderColor: BrandColors.warmAmber,
    borderRadius: Radius.medium,
    paddingVertical: Spacing.one,
    paddingHorizontal: Spacing.three,
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
  multilineInput: { minHeight: 80, textAlignVertical: 'top' },
  error: { color: '#a6483a', marginBottom: Spacing.two },
  button: {
    backgroundColor: BrandColors.warmAmber,
    borderRadius: Radius.medium,
    paddingVertical: Spacing.two,
    paddingHorizontal: Spacing.four,
    alignItems: 'center',
    alignSelf: 'flex-start',
    marginTop: Spacing.two,
  },
  buttonDisabled: { opacity: 0.6 },
  buttonText: { color: '#fff', fontWeight: '600' },
  publicUrl: { marginBottom: Spacing.two },
  qrBox: { alignItems: 'flex-start', marginBottom: Spacing.two },
  list: { gap: Spacing.one },
  questionRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    paddingVertical: Spacing.two,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: '#eee',
    gap: Spacing.two,
  },
  questionInfo: { flex: 1, gap: Spacing.half },
  questionActions: { flexDirection: 'row', gap: Spacing.two, alignItems: 'center' },
  required: { color: '#a6483a' },
  destructiveText: { color: '#a6483a', fontWeight: '600' },
});
