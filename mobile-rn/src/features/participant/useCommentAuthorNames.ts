import { useMemo } from 'react';

import { useParticipants } from '@/features/participant/useParticipant';
import { useCurrentPerson } from '@/features/person/useCurrentPerson';

/**
 * Author names for a Production's comments, by Person id - the same rule
 * the rehearsal detail screen applies (0f4b3e0, "show comment author names
 * instead of person ids"): an ACTIVE, Person-subject Participant's
 * display name, else family + given name; the current Person's own name
 * from GET /me. An author absent from the map is shown as 名前未設定 by
 * ScheduleCommentList.
 *
 * PR #7 review (R7): added for the timetable item detail screen, which
 * still received no names at all, so every comment there read 名前未設定.
 */
export function useCommentAuthorNames(productionId: string | undefined): Map<string, string> {
  const participantsQuery = useParticipants(productionId);
  const currentPersonQuery = useCurrentPerson();

  return useMemo(() => {
    const map = new Map<string, string>();
    for (const participant of participantsQuery.data ?? []) {
      if (participant.subject_type !== 'PERSON' || participant.status !== 'ACTIVE') continue;
      const fullName = [participant.person_family_name, participant.person_given_name].filter(Boolean).join(' ');
      const name = participant.display_name?.trim() || fullName;
      if (name) map.set(participant.subject_id, name);
    }
    const me = currentPersonQuery.data;
    if (me?.id) {
      const myName = [me.family_name, me.given_name].filter(Boolean).join(' ');
      if (myName) map.set(me.id, myName);
    }
    return map;
  }, [participantsQuery.data, currentPersonQuery.data]);
}
