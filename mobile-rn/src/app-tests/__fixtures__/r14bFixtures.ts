import type { ManagedParticipantInvitation, MyParticipantInvitation, Participant } from '@/types/api';

/**
 * PR #7 R14-B stage 9: fixtures in the exact shapes the stage 1-8 Backend
 * answers (ParticipantResult with the §7.3 disclosure, the managers'
 * invitation list with the §7.1 fields) and the invitation screen's answer
 * (GET /me/participant-invitations/{id}, invitation policy 2026-11-27).
 */
export function participantRow(overrides: Partial<Participant> = {}): Participant {
  return {
    id: 'participant-1',
    production_id: 'prod-1',
    subject_type: 'PERSON',
    subject_id: 'person-9',
    participant_type: 'CAST',
    status: 'ACTIVE',
    created_at: '',
    updated_at: '',
    remarks: null,
    display_name: '出演 太郎',
    person_family_name: '出演',
    person_given_name: '太郎',
    email: 'member@example.com',
    enrollment_source: 'INVITATION',
    consent_status: 'CONFIRMED',
    consented_at: '2026-11-01T00:00:00+00:00',
    ...overrides,
  };
}

export function managedInvitation(overrides: Partial<ManagedParticipantInvitation> = {}): ManagedParticipantInvitation {
  return {
    id: 'invitation-1',
    production_id: 'prod-1',
    email: 'invitee@example.com',
    name: '招待 花子',
    invited_by_person_id: 'person-1',
    participant_type: 'CAST',
    remarks: null,
    status: 'PENDING',
    created_at: '',
    expires_at: '2099-01-01T00:00:00+00:00',
    consumed_at: null,
    is_expired: false,
    effective_status: 'PENDING',
    responded_at: null,
    close_reason: null,
    closed_at: null,
    resend_count: 0,
    resend_remaining: 5,
    send_status: 'SENT',
    last_sent_at: '2026-11-18T01:00:00+00:00',
    send_limit: { sent_in_30_days: 1, remaining_in_30_days: 5, available_at: null },
    ...overrides,
  };
}

/** GET /me/participant-invitations/{id} (invitation policy 2026-11-27). */
export function myInvitation(overrides: Partial<MyParticipantInvitation> = {}): MyParticipantInvitation {
  return {
    id: 'invitation-1',
    production_id: 'prod-1',
    production_name: '踊れチュパカブラ',
    participant_type: 'CAST',
    status: 'PENDING',
    ...overrides,
  };
}

/** A substring matcher for toHaveTextContent (a string argument compares the whole text). */
export function contains(text: string): RegExp {
  return new RegExp(text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
}

/** One answer of the mocked fetch (for mockImplementationOnce). */
export function apiAnswer(status: number, body: unknown) {
  return async () => ({
    ok: status >= 200 && status < 300,
    status,
    text: async () => (body === null ? '' : JSON.stringify(body)),
    json: async () => body,
  });
}

/** The WP REST error envelope the Backend answers. */
export function apiError(status: number, code: string, data: Record<string, unknown> = {}) {
  return apiAnswer(status, { code, message: code, data: { status, ...data } });
}

/** The fetch calls made to a URL suffix with a method. */
export function callsTo(suffix: string, method: string): [string, RequestInit][] {
  return (global.fetch as jest.Mock).mock.calls.filter(
    ([url, init]: [string, RequestInit?]) => String(url).endsWith(suffix) && (init?.method ?? 'GET') === method
  );
}
