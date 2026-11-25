import type { Participant } from '@/types/api';

import { isActivePersonParticipant } from './participantGuards';

const row = (overrides: Partial<Participant>): Participant => ({
  id: 'p',
  production_id: 'prod-1',
  subject_type: 'PERSON',
  subject_id: 'person-1',
  participant_type: 'CAST',
  status: 'ACTIVE',
  created_at: '',
  updated_at: '',
  remarks: null,
  display_name: null,
  person_family_name: null,
  person_given_name: null,
  email: null,
  enrollment_source: null,
  consent_status: null,
  consented_at: null,
  ...overrides,
});

/** PR #7 R14-B stage 9 (design doc §7.3): rows whose Person id is withheld are never keyed by it. */
describe('isActivePersonParticipant', () => {
  it('is true only for an ACTIVE PERSON row with a known id', () => {
    expect(isActivePersonParticipant(row({}))).toBe(true);
    expect(isActivePersonParticipant(row({ subject_id: null }))).toBe(false);
    expect(isActivePersonParticipant(row({ status: 'WITHDRAWN', subject_id: null }))).toBe(false);
    expect(isActivePersonParticipant(row({ status: 'INACTIVE' }))).toBe(false);
    expect(isActivePersonParticipant(row({ subject_type: 'NAME_ONLY' }))).toBe(false);
  });
});
