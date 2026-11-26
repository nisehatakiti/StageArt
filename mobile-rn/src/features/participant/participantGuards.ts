import type { Participant } from '@/types/api';

/** A Participant row whose Person id is known (it is withheld - null - on an ended row). */
export type ActivePersonParticipant = Participant & { subject_type: 'PERSON'; subject_id: string };

/**
 * PR #7 R14-B (design doc §7.3): an ACTIVE PERSON row with its Person id.
 * The participant list may carry rows whose `subject_id` is null (an
 * ended row shown to a manager), so screens that key anything by the
 * Person id use this instead of comparing `subject_type` / `status` alone.
 * An ACTIVE row always carries its id; the null check only keeps a
 * withheld id from ever being used as one.
 */
export function isActivePersonParticipant(participant: Participant): participant is ActivePersonParticipant {
  return participant.subject_type === 'PERSON' && participant.status === 'ACTIVE' && participant.subject_id !== null;
}
