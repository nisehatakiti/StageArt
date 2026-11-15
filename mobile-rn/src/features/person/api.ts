import type { ApiClient } from '@/api/client';
import type { PersonSummary } from '@/types/api';

/**
 * StageArt メンバー管理: Person ID検索 (担当者権限をメンバー管理へ統合・整理
 * §1/§2-A instruction) - GET /people/{id}, the first Person lookup-by-id
 * endpoint in StageArt (confirmed via Backend investigation: none existed
 * before). Used to let a PrimaryManager/代理人 confirm "is this the right
 * person?" before adding them as a Participant via the already-existing
 * POST /productions/{id}/participants (subject_type: 'PERSON').
 */
export function fetchPersonById(client: ApiClient, personId: string): Promise<PersonSummary> {
  return client.get<PersonSummary>(`/people/${encodeURIComponent(personId)}`);
}

/**
 * StageArt メール招待によるProductionParticipant追加機能 §8: GET /people?
 * email=&production_id= - production_id is required Backend-side because
 * canManageParticipants() (the permission this search is gated by) is
 * itself Production-scoped, so a bare email-only search has nothing to
 * check that capability against (see SearchPersonByEmailUseCase.php's
 * own docblock for why this differs from the bare `GET /people?email=`
 * shape).
 */
export function searchPersonByEmail(client: ApiClient, email: string, productionId: string): Promise<PersonSummary> {
  return client.get<PersonSummary>('/people', { email, production_id: productionId });
}
