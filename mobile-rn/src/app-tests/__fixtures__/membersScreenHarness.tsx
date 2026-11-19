import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render } from '@testing-library/react-native';

import { AuthProvider } from '@/auth/AuthContext';
import { OrganizationProvider } from '@/features/organization/OrganizationContext';
import type { Organization } from '@/types/api';

import { mockFetchRoutes } from './homeFixtures';
import OrganizationMembersScreen from '../../app/(app)/organizations/[id]/members';

/**
 * Shared harness for the web-organization-members-delegate-areas-* tests
 * (one it() per file, this codebase's convention for query-driven screen
 * tests). Each test file still declares its own jest.mock()s - expo-router's
 * useLocalSearchParams must return { id: 'org-1' } - since those are
 * hoisted per file.
 *
 * Roster of org-1: person-1 Owner, person-2 plain member, person-3 delegate.
 */
export const ROSTER = [
  { membership_id: 'm-1', person_id: 'person-1', person_family_name: '舞台', person_given_name: '芸術', email: 'owner@example.com', role_key: 'OWNER', joined_at: '2026-01-01T00:00:00+09:00', remarks: null },
  { membership_id: 'm-2', person_id: 'person-2', person_family_name: '山田', person_given_name: '太郎', email: 'yamada@example.com', role_key: 'MEMBER', joined_at: '2026-01-02T00:00:00+09:00', remarks: null },
  { membership_id: 'm-3', person_id: 'person-3', person_family_name: '佐藤', person_given_name: '花子', email: 'sato@example.com', role_key: 'ORGANIZATION_MANAGER', joined_at: '2026-01-03T00:00:00+09:00', remarks: null },
];

export const AREAS = ['MEMBERS', 'PRODUCTIONS', 'ACCOUNTING'] as const;

type Route = { test: (url: string) => boolean; status: number; body: unknown };

export function renderMembersAs(organization: Organization, routes: Route[] = []) {
  mockFetchRoutes([
    ...routes,
    { test: (u) => u.endsWith('/organizations'), status: 200, body: [organization] },
    { test: (u) => u.endsWith('/organizations/org-1/members'), status: 200, body: ROSTER },
    { test: (u) => u.endsWith('/organizations/org-1/invitations'), status: 200, body: [] },
  ]);

  const queryClient = new QueryClient();
  return render(
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <OrganizationProvider>
          <OrganizationMembersScreen />
        </OrganizationProvider>
      </AuthProvider>
    </QueryClientProvider>
  );
}

/** The fetch calls made so far, as [url, method, parsed JSON body]. */
export function fetchCalls(): [string, string, unknown][] {
  return (global.fetch as jest.Mock).mock.calls.map(([url, options]: [unknown, RequestInit | undefined]) => [
    String(url),
    options?.method ?? 'GET',
    typeof options?.body === 'string' ? JSON.parse(options.body) : undefined,
  ]);
}
