import type { Participant, TimetableItem } from '@/types/api';

/**
 * Personal Highlight is Client State, derived - never a Server field
 * (§30: "Itemそのものに、isMine等をBackendから追加要求しない"). Mirrors the
 * Flutter reference implementation's `TimetableItem.relatesToMe` exactly.
 */
export function deriveMyParticipantTypes(participants: Participant[], myPersonId: string): Set<string> {
  return new Set(
    participants
      .filter((p) => p.subject_type === 'PERSON' && p.subject_id === myPersonId && p.status === 'ACTIVE')
      .map((p) => p.participant_type)
  );
}

/**
 * §5/§6: Highlight only changes emphasis - callers must never use this
 * to remove an Item from the rendered list (Shared Visibility
 * Principle). True if I am directly targeted by Person, or my own
 * Participant Type matches this Item's Role.
 */
export function relatesToMe(
  item: TimetableItem,
  myPersonId: string | null,
  myParticipantTypes: Set<string>
): boolean {
  if (!myPersonId) {
    return false;
  }

  if (item.target_person_ids.includes(myPersonId)) {
    return true;
  }

  return item.participant_type !== null && myParticipantTypes.has(item.participant_type);
}
