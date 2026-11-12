import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { useAuth } from '@/auth/AuthContext';
import type { Question, QuestionnaireAnswerInput } from '@/types/api';

import {
  addQuestion,
  closeQuestionnaire,
  createQuestionnaire,
  deleteQuestion,
  fetchPublicQuestionnaire,
  fetchQuestionnaire,
  fetchQuestionnaireResults,
  publishQuestionnaire,
  reorderQuestions,
  submitQuestionnaireResponse,
  updateQuestion,
  updateQuestionnaire,
} from './api';

export function useQuestionnaire(productionId: string | undefined) {
  const { apiClient, status } = useAuth();

  return useQuery({
    queryKey: ['questionnaire', productionId],
    queryFn: () => fetchQuestionnaire(apiClient, productionId as string),
    enabled: status === 'authenticated' && !!productionId,
    retry: false,
  });
}

export function useQuestionnaireResults(productionId: string | undefined, enabled: boolean) {
  const { apiClient, status } = useAuth();

  return useQuery({
    queryKey: ['questionnaire-results', productionId],
    queryFn: () => fetchQuestionnaireResults(apiClient, productionId as string),
    enabled: status === 'authenticated' && !!productionId && enabled,
  });
}

export function useCreateQuestionnaire(productionId: string | undefined) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (fields: { title: string; description?: string | null }) => createQuestionnaire(apiClient, productionId as string, fields),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['questionnaire', productionId] }),
  });
}

export function useUpdateQuestionnaire(productionId: string | undefined) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (fields: { title: string; description: string | null; responseEndAt: string | null }) =>
      updateQuestionnaire(apiClient, productionId as string, fields),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['questionnaire', productionId] }),
  });
}

export function usePublishQuestionnaire(productionId: string | undefined) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: () => publishQuestionnaire(apiClient, productionId as string),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['questionnaire', productionId] }),
  });
}

export function useCloseQuestionnaire(productionId: string | undefined) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: () => closeQuestionnaire(apiClient, productionId as string),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['questionnaire', productionId] }),
  });
}

export function useAddQuestion(productionId: string | undefined) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (fields: { text: string; type: Question['type']; required: boolean; choices: string[] }) =>
      addQuestion(apiClient, productionId as string, fields),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['questionnaire', productionId] }),
  });
}

export function useUpdateQuestion(productionId: string | undefined, questionId: string | undefined) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (fields: { text: string; required: boolean; choices: { id: string | null; label: string }[] }) =>
      updateQuestion(apiClient, productionId as string, questionId as string, fields),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['questionnaire', productionId] }),
  });
}

export function useDeleteQuestion(productionId: string | undefined) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (questionId: string) => deleteQuestion(apiClient, productionId as string, questionId),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['questionnaire', productionId] }),
  });
}

export function useReorderQuestions(productionId: string | undefined) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (orderedQuestionIds: string[]) => reorderQuestions(apiClient, productionId as string, orderedQuestionIds),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['questionnaire', productionId] }),
  });
}

export function usePublicQuestionnaire(productionSlug: string | undefined) {
  return useQuery({
    queryKey: ['public-questionnaire', productionSlug],
    queryFn: () => fetchPublicQuestionnaire(productionSlug as string),
    enabled: !!productionSlug,
    retry: false,
  });
}

export function useSubmitQuestionnaireResponse(productionSlug: string | undefined) {
  return useMutation({
    mutationFn: (answers: QuestionnaireAnswerInput[]) => submitQuestionnaireResponse(productionSlug as string, answers),
  });
}
