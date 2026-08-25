import type { ApiClient } from '@/api/client';
import type { MembershipRequest, MyMembership } from '@/types/api';

/** Exactly one of `organizationId`/`joinKeyCode` should be set by the
 * caller - mirrors RequestOrganizationMembershipCommand.php's own dual
 * entry point (search-based vs Join Key-based). */
export function requestOrganizationMembership(
  client: ApiClient,
  fields: { organizationId?: string; joinKeyCode?: string }
): Promise<MembershipRequest> {
  return client.post<MembershipRequest>('/membership-requests', {
    organization_id: fields.organizationId,
    join_key_code: fields.joinKeyCode,
  });
}

export function approveMembershipRequest(client: ApiClient, membershipId: string): Promise<MembershipRequest> {
  return client.post<MembershipRequest>(`/membership-requests/${membershipId}/approve`);
}

export function rejectMembershipRequest(client: ApiClient, membershipId: string): Promise<MembershipRequest> {
  return client.post<MembershipRequest>(`/membership-requests/${membershipId}/reject`);
}

export function fetchPendingMembershipRequests(client: ApiClient, organizationId: string): Promise<MembershipRequest[]> {
  return client.get<MembershipRequest[]>(`/organizations/${organizationId}/membership-requests`);
}

/** GET /me/memberships - every status, not just ACTIVE (see
 * src/types/api.ts's MyMembership docblock). */
export function fetchMyMemberships(client: ApiClient): Promise<MyMembership[]> {
  return client.get<MyMembership[]>('/me/memberships');
}
