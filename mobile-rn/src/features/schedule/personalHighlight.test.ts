import type { Participant, TimetableItem } from '@/types/api';

import { deriveMyParticipantTypes, relatesToMe } from './personalHighlight';

const baseItem: TimetableItem = {
  id: 'item-1',
  timetable_id: 'tt-1',
  title: 'シュート',
  description: null,
  start_date_time: '2026-08-18T10:00:00+09:00',
  end_date_time: '2026-08-18T12:00:00+09:00',
  display_order: null,
  category: '仕込み',
  venue: '舞台',
  participant_type: null,
  target_person_ids: [],
  notes: null,
  created_at: '',
  updated_at: '',
};

describe('deriveMyParticipantTypes', () => {
  const participants: Participant[] = [
    {
      id: 'p1',
      production_id: 'prod-1',
      subject_type: 'PERSON',
      subject_id: 'person-1',
      participant_type: 'STAFF',
      status: 'ACTIVE',
      created_at: '',
      updated_at: '',
    },
    {
      id: 'p2',
      production_id: 'prod-1',
      subject_type: 'PERSON',
      subject_id: 'person-1',
      participant_type: 'LIGHTING',
      status: 'CANCELLED',
      created_at: '',
      updated_at: '',
    },
    {
      id: 'p3',
      production_id: 'prod-1',
      subject_type: 'PERSON',
      subject_id: 'person-2',
      participant_type: 'CAST',
      status: 'ACTIVE',
      created_at: '',
      updated_at: '',
    },
  ];

  it('includes only ACTIVE Participant rows for the given PersonId', () => {
    const types = deriveMyParticipantTypes(participants, 'person-1');
    expect(types).toEqual(new Set(['STAFF']));
  });

  it('excludes other Persons entirely', () => {
    const types = deriveMyParticipantTypes(participants, 'person-2');
    expect(types).toEqual(new Set(['CAST']));
  });
});

describe('relatesToMe (§18 Role/Person 3 patterns, §5/§6 Highlight != Filter)', () => {
  it('Pattern A (Participant Type only): highlights when my Participant Type matches', () => {
    const item = { ...baseItem, participant_type: 'STAFF', target_person_ids: [] };
    expect(relatesToMe(item, 'person-1', new Set(['STAFF']))).toBe(true);
    expect(relatesToMe(item, 'person-1', new Set(['CAST']))).toBe(false);
  });

  it('Pattern B (Person only): highlights when I am directly targeted', () => {
    const item = { ...baseItem, participant_type: null, target_person_ids: ['person-1'] };
    expect(relatesToMe(item, 'person-1', new Set())).toBe(true);
    expect(relatesToMe(item, 'person-2', new Set())).toBe(false);
  });

  it('Pattern C (Participant Type + Person): highlights on either condition', () => {
    const item = { ...baseItem, participant_type: 'STAFF', target_person_ids: ['person-9'] };
    expect(relatesToMe(item, 'person-1', new Set(['STAFF']))).toBe(true);
    expect(relatesToMe(item, 'person-9', new Set())).toBe(true);
    expect(relatesToMe(item, 'person-2', new Set(['CAST']))).toBe(false);
  });

  it('returns false with no myPersonId (never highlights before Person identity is known)', () => {
    const item = { ...baseItem, target_person_ids: ['person-1'] };
    expect(relatesToMe(item, null, new Set())).toBe(false);
  });

  it('an Item that does not relate to me is still a valid Item (Highlight != Visibility filter)', () => {
    const item = { ...baseItem, participant_type: 'LIGHTING', target_person_ids: [] };
    expect(relatesToMe(item, 'person-1', new Set(['STAFF']))).toBe(false);
    // The Item object itself is untouched/unfiltered - relatesToMe only
    // ever answers "should this be emphasized", never removes it from a list.
    expect(item.id).toBe('item-1');
  });
});
