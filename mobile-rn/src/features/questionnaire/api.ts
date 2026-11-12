import type { ApiClient } from '@/api/client';
import { publicGet, publicPost } from '@/api/publicClient';
import type { Question, Questionnaire, QuestionnaireAnswerInput, QuestionnaireResults, PublicQuestionnaire } from '@/types/api';

// --- 管理API (認証必須。実際の権限判定はBackend側 - QuestionnaireCapability::MANAGE) ---

export function fetchQuestionnaire(client: ApiClient, productionId: string): Promise<Questionnaire> {
  return client.get<Questionnaire>(`/productions/${productionId}/questionnaire`);
}

export function createQuestionnaire(client: ApiClient, productionId: string, fields: { title: string; description?: string | null }): Promise<Questionnaire> {
  return client.post<Questionnaire>(`/productions/${productionId}/questionnaire`, {
    title: fields.title,
    description: fields.description,
  });
}

export function updateQuestionnaire(
  client: ApiClient,
  productionId: string,
  fields: { title: string; description: string | null; responseEndAt: string | null }
): Promise<Questionnaire> {
  return client.put<Questionnaire>(`/productions/${productionId}/questionnaire`, {
    title: fields.title,
    description: fields.description,
    response_end_at: fields.responseEndAt,
  });
}

export function publishQuestionnaire(client: ApiClient, productionId: string): Promise<Questionnaire> {
  return client.patch<Questionnaire>(`/productions/${productionId}/questionnaire/publish`);
}

export function closeQuestionnaire(client: ApiClient, productionId: string): Promise<Questionnaire> {
  return client.patch<Questionnaire>(`/productions/${productionId}/questionnaire/close`);
}

export function fetchQuestionnaireResults(client: ApiClient, productionId: string): Promise<QuestionnaireResults> {
  return client.get<QuestionnaireResults>(`/productions/${productionId}/questionnaire/results`);
}

export function addQuestion(
  client: ApiClient,
  productionId: string,
  fields: { text: string; type: Question['type']; required: boolean; choices: string[] }
): Promise<Question> {
  return client.post<Question>(`/productions/${productionId}/questionnaire/questions`, {
    text: fields.text,
    type: fields.type,
    required: fields.required,
    choices: fields.choices,
  });
}

/** `choices[].id` keeps an existing choice (required once the Question
 * already has real answers - the Backend 409s with
 * `stageart_question_edit_locked` if a previously-answered choice id is
 * missing); `id: null` creates a brand-new choice. */
export function updateQuestion(
  client: ApiClient,
  productionId: string,
  questionId: string,
  fields: { text: string; required: boolean; choices: { id: string | null; label: string }[] }
): Promise<Question> {
  return client.put<Question>(`/productions/${productionId}/questionnaire/questions/${questionId}`, {
    text: fields.text,
    required: fields.required,
    choices: fields.choices,
  });
}

export function deleteQuestion(client: ApiClient, productionId: string, questionId: string): Promise<void> {
  return client.delete<void>(`/productions/${productionId}/questionnaire/questions/${questionId}`);
}

export function reorderQuestions(client: ApiClient, productionId: string, orderedQuestionIds: string[]): Promise<Question[]> {
  return client.post<Question[]>(`/productions/${productionId}/questionnaire/questions/reorder`, {
    question_ids: orderedQuestionIds,
  });
}

// --- 公開API (認証不要。Production Slugのみで解決 - organizationSlugはURL上の飾り) ---

export function fetchPublicQuestionnaire(productionSlug: string): Promise<PublicQuestionnaire> {
  return publicGet<PublicQuestionnaire>(`/questionnaires/by-slug/${encodeURIComponent(productionSlug)}`);
}

export function submitQuestionnaireResponse(productionSlug: string, answers: QuestionnaireAnswerInput[]): Promise<{ submitted: true }> {
  return publicPost<{ submitted: true }>(`/questionnaires/by-slug/${encodeURIComponent(productionSlug)}/responses`, { answers });
}
