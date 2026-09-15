import { useLocalSearchParams, useRouter, type Href } from 'expo-router';
import { useEffect, useState } from 'react';
import { ActivityIndicator, StyleSheet, TouchableOpacity, View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { ThemedTextInput } from '@/components/themed-text-input';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import { useAddQuestion, useQuestionnaire, useUpdateQuestion } from '@/features/questionnaire/useQuestionnaire';
import { useProduction } from '@/features/production/useProductions';
import type { Question } from '@/types/api';
import { getErrorMessage } from '@/utils/errorMessage';

const TYPE_OPTIONS: { value: Question['type']; label: string }[] = [
  { value: 'SINGLE_CHOICE', label: '単一選択' },
  { value: 'MULTIPLE_CHOICE', label: '複数選択' },
  { value: 'RATING_5', label: '5段階評価' },
  { value: 'FREE_TEXT', label: '自由記述' },
  { value: 'YES_NO', label: 'Yes / No' },
];

const CHOICE_BASED_TYPES: Question['type'][] = ['SINGLE_CHOICE', 'MULTIPLE_CHOICE'];

type ChoiceRow = { id: string | null; label: string; key: string };

let choiceRowSeq = 0;
function newChoiceRow(id: string | null = null, label = ''): ChoiceRow {
  choiceRowSeq += 1;
  return { id, label, key: `choice-${choiceRowSeq}` };
}

/**
 * アンケート実装指示書 §6-§8/§30: 質問の追加・編集。`questionId`が無ければ新規
 * 追加、あれば編集（既存回答がある質問の破壊的な変更 - 選択肢の削除等 - は
 * Backend側が409 stageart_question_edit_lockedで拒否するので、そのメッセージ
 * をそのまま表示する）。
 */
export default function ProductionQuestionEditScreen() {
  const { id, questionId } = useLocalSearchParams<{ id: string; questionId?: string }>();
  const router = useRouter();
  const productionQuery = useProduction(id);
  const production = productionQuery.data;
  const questionnaireQuery = useQuestionnaire(id);
  const existingQuestion = questionnaireQuery.data?.questions.find((question) => question.id === questionId) ?? null;
  const isEditing = !!questionId;

  const addQuestion = useAddQuestion(id);
  const updateQuestion = useUpdateQuestion(id, questionId);

  const isPrimaryManager = !!production?.is_primary_manager;
  const canManage = isPrimaryManager || production?.delegate_role === 'QUESTIONNAIRE_MANAGER';

  const [text, setText] = useState('');
  const [type, setType] = useState<Question['type']>('SINGLE_CHOICE');
  const [required, setRequired] = useState(false);
  const [choices, setChoices] = useState<ChoiceRow[]>(() => [newChoiceRow(), newChoiceRow()]);
  const [initialized, setInitialized] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  useEffect(() => {
    if (isEditing && existingQuestion && !initialized) {
      setText(existingQuestion.text);
      setType(existingQuestion.type);
      setRequired(existingQuestion.required);
      setChoices(
        existingQuestion.choices.length > 0
          ? existingQuestion.choices.map((choice) => newChoiceRow(choice.id, choice.label))
          : [newChoiceRow(), newChoiceRow()]
      );
      setInitialized(true);
    }
  }, [isEditing, existingQuestion, initialized]);

  const isChoiceBased = CHOICE_BASED_TYPES.includes(type);
  const nonEmptyChoices = choices.filter((choice) => choice.label.trim().length > 0);
  const canSubmit = !!text.trim() && (!isChoiceBased || nonEmptyChoices.length >= 1);

  function updateChoiceLabel(key: string, label: string) {
    setChoices((previous) => previous.map((choice) => (choice.key === key ? { ...choice, label } : choice)));
  }

  function addChoiceRow() {
    setChoices((previous) => [...previous, newChoiceRow()]);
  }

  function removeChoiceRow(key: string) {
    setChoices((previous) => previous.filter((choice) => choice.key !== key));
  }

  async function handleSubmit() {
    setErrorMessage(null);
    try {
      if (isEditing) {
        await updateQuestion.mutateAsync({
          text: text.trim(),
          required,
          choices: isChoiceBased ? nonEmptyChoices.map((choice) => ({ id: choice.id, label: choice.label.trim() })) : [],
        });
      } else {
        await addQuestion.mutateAsync({
          text: text.trim(),
          type,
          required,
          choices: isChoiceBased ? nonEmptyChoices.map((choice) => choice.label.trim()) : [],
        });
      }
      router.push(`/productions/${id}/questionnaire` as Href);
    } catch (error) {
      setErrorMessage(getErrorMessage(error));
    }
  }

  if (productionQuery.isLoading || (isEditing && questionnaireQuery.isLoading)) {
    return (
      <>
        <ActivityIndicator testID="questionnaire-question-loading" />
      </>
    );
  }

  if (!production) {
    return (
      <>
        <ThemedText testID="questionnaire-question-production-not-found">この公演が見つかりません。</ThemedText>
      </>
    );
  }

  if (!canManage) {
    return (
      <>
        <ThemedText testID="questionnaire-question-forbidden">
          アンケート管理はPrimaryManagerまたはアンケート管理の権限を持つ担当者のみ利用できます。
        </ThemedText>
      </>
    );
  }

  const submitting = addQuestion.isPending || updateQuestion.isPending;

  return (
    <>
      <ThemedText type="title" style={styles.pageTitle}>
        {isEditing ? '質問を編集' : '質問を追加'}
      </ThemedText>

      <ThemedText type="small" themeColor="textSecondary">
        質問文
      </ThemedText>
      <ThemedTextInput testID="questionnaire-question-text" value={text} onChangeText={setText} style={styles.input} />

      <ThemedText type="small" themeColor="textSecondary">
        質問タイプ
      </ThemedText>
      <View style={styles.typeOptions}>
        {TYPE_OPTIONS.map((option) => (
          <TouchableOpacity
            key={option.value}
            testID={`questionnaire-question-type-${option.value}`}
            onPress={() => setType(option.value)}
            disabled={isEditing}
            style={[styles.typeOption, type === option.value && styles.typeOptionSelected, isEditing && styles.typeOptionDisabled]}
            accessibilityState={{ selected: type === option.value }}
          >
            <ThemedText type={type === option.value ? 'smallBold' : 'small'}>{option.label}</ThemedText>
          </TouchableOpacity>
        ))}
      </View>
      {isEditing && (
        <ThemedText type="small" themeColor="textSecondary" style={styles.caption}>
          質問タイプは作成後に変更できません（既存の回答の意味を壊さないため）。
        </ThemedText>
      )}

      <TouchableOpacity testID="questionnaire-question-required-toggle" onPress={() => setRequired((value) => !value)} style={styles.requiredRow}>
        <ThemedText>{required ? '☑' : '☐'} 必須にする</ThemedText>
      </TouchableOpacity>

      {isChoiceBased && (
        <>
          <ThemedText type="small" themeColor="textSecondary" style={styles.choicesLabel}>
            選択肢
          </ThemedText>
          {choices.map((choice, index) => (
            <View key={choice.key} style={styles.choiceRow}>
              <ThemedTextInput
                testID={`questionnaire-question-choice-${index}`}
                value={choice.label}
                onChangeText={(value) => updateChoiceLabel(choice.key, value)}
                style={[styles.input, styles.choiceInput]}
              />
              <TouchableOpacity testID={`questionnaire-question-choice-remove-${index}`} onPress={() => removeChoiceRow(choice.key)}>
                <ThemedText type="link">削除</ThemedText>
              </TouchableOpacity>
            </View>
          ))}
          <TouchableOpacity testID="questionnaire-question-choice-add" onPress={addChoiceRow}>
            <ThemedText type="link">＋ 選択肢を追加</ThemedText>
          </TouchableOpacity>
        </>
      )}

      {errorMessage && (
        <ThemedText testID="questionnaire-question-error" style={styles.error}>
          {errorMessage}
        </ThemedText>
      )}

      <View style={styles.actions}>
        <TouchableOpacity
          testID="questionnaire-question-submit"
          onPress={handleSubmit}
          disabled={!canSubmit || submitting}
          style={[styles.button, (!canSubmit || submitting) && styles.buttonDisabled]}
        >
          {submitting ? <ActivityIndicator color="#fff" /> : <ThemedText style={styles.buttonText}>{isEditing ? '保存する' : '追加する'}</ThemedText>}
        </TouchableOpacity>
        <TouchableOpacity testID="questionnaire-question-cancel" onPress={() => router.push(`/productions/${id}/questionnaire` as Href)}>
          <ThemedText type="link">キャンセル</ThemedText>
        </TouchableOpacity>
      </View>
    </>
  );
}

const styles = StyleSheet.create({
  pageTitle: { marginBottom: Spacing.two },
  input: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    paddingHorizontal: Spacing.three,
    paddingVertical: Spacing.two,
    fontSize: 16,
    marginBottom: Spacing.two,
  },
  typeOptions: { flexDirection: 'row', flexWrap: 'wrap', gap: Spacing.one, marginBottom: Spacing.one },
  typeOption: {
    paddingVertical: Spacing.one,
    paddingHorizontal: Spacing.two,
    borderRadius: Radius.medium,
    borderWidth: 1,
    borderColor: '#ccc',
  },
  typeOptionSelected: { borderColor: BrandColors.warmAmber, backgroundColor: '#FBEFDD' },
  typeOptionDisabled: { opacity: 0.5 },
  caption: { marginBottom: Spacing.two },
  requiredRow: { marginBottom: Spacing.two },
  choicesLabel: { marginTop: Spacing.one },
  choiceRow: { flexDirection: 'row', alignItems: 'center', gap: Spacing.two },
  choiceInput: { flex: 1 },
  error: { color: '#a6483a', marginTop: Spacing.two, marginBottom: Spacing.two },
  actions: { flexDirection: 'row', alignItems: 'center', gap: Spacing.four, marginTop: Spacing.three },
  button: {
    backgroundColor: BrandColors.warmAmber,
    borderRadius: Radius.medium,
    paddingVertical: Spacing.two,
    paddingHorizontal: Spacing.four,
    alignItems: 'center',
  },
  buttonDisabled: { opacity: 0.6 },
  buttonText: { color: '#fff', fontWeight: '600' },
});
