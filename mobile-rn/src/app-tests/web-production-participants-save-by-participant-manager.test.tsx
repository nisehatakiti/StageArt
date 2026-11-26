import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

import { AuthProvider } from '@/auth/AuthContext';
import { OrganizationProvider } from '@/features/organization/OrganizationContext';
import type { Participant, Production } from '@/types/api';

import { orgOne, productionOne, projectOne } from './__fixtures__/homeFixtures';
import ProductionParticipantsScreen from '../app/(app)/productions/[id]/participants';

/** AuthProvider boot (refresh) plus the screen's queries can take longer than the default 1s waitFor. */
const FIRST_RENDER = { timeout: 10000 };

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

const mockRouter = { push: jest.fn(), replace: jest.fn(), setParams: jest.fn(), back: jest.fn() };
const mockParams = { id: 'prod-1' };

jest.mock('expo-router', () => ({
  ...jest.requireActual('expo-router'),
  useRouter: () => mockRouter,
  useLocalSearchParams: () => mockParams,
}));

const member: Participant = {
  id: 'participant-1',
  production_id: 'prod-1',
  subject_type: 'PERSON',
  subject_id: 'person-9',
  participant_type: 'CAST',
  status: 'ACTIVE',
  created_at: '',
  updated_at: '',
  remarks: null,
  display_name: '山田花子',
  person_family_name: null,
  person_given_name: null,
  email: null,
  // R14-B: a member who accepted an invitation.
  enrollment_source: 'INVITATION',
  consent_status: 'CONFIRMED',
  consented_at: '2026-01-01T00:00:00+09:00',
};

type Call = { method: string; url: string; body: unknown };

/**
 * A fetch mock that keeps the Participant's state, so a PUT is visible to
 * the next GET (the screen refetches after saving), and records every
 * request.
 */
function mockApi(production: Production): Call[] {
  const calls: Call[] = [];
  let participant = { ...member };

  global.fetch = jest.fn(async (input: unknown, init?: RequestInit) => {
    const url = String(input);
    const method = (init?.method ?? 'GET').toUpperCase();
    const body = typeof init?.body === 'string' ? JSON.parse(init.body) : undefined;
    calls.push({ method, url, body });

    let status = 200;
    let payload: unknown = [];

    if (url.endsWith('/auth/refresh')) {
      payload = { access_token: 'mock-access-token', refresh_token: 'mock-refresh-token', token_type: 'Bearer', expires_in: 3600 };
    } else if (url.endsWith('/organizations')) {
      payload = [orgOne];
    } else if (url.endsWith('/projects')) {
      payload = [projectOne];
    } else if (url.endsWith('/productions/prod-1') && method === 'GET') {
      payload = production;
    } else if (url.endsWith('/productions/prod-1') && method === 'PUT') {
      status = 403;
      payload = { code: 'stageart_production_access_denied', message: 'forbidden', data: { status: 403 } };
    } else if (url.endsWith('/productions/prod-1/participants')) {
      payload = [participant];
    } else if (url.endsWith(`/participants/${member.id}`) && method === 'PUT') {
      participant = { ...participant, participant_type: (body as { participant_type: string }).participant_type };
      payload = participant;
    }

    return {
      ok: status >= 200 && status < 300,
      status,
      text: async () => JSON.stringify(payload),
      json: async () => payload,
    } as Response;
  }) as jest.Mock;

  return calls;
}

function renderScreen(production: Production) {
  const calls = mockApi(production);

  render(
    <QueryClientProvider client={new QueryClient()}>
      <AuthProvider>
        <OrganizationProvider>
          <ProductionParticipantsScreen />
        </OrganizationProvider>
      </AuthProvider>
    </QueryClientProvider>
  );

  return calls;
}

const participantManagerOnly: Production = {
  ...productionOne,
  is_primary_manager: false,
  delegate_role: 'PARTICIPANT_MANAGER',
  delegate_roles: ['PARTICIPANT_MANAGER'],
};

/**
 * PR #9 review No.4: a PARTICIPANT_MANAGER-only delegate saves the member
 * changes without calling PUT /productions/{id} (メンバー情報公開日時 is a
 * Production field only the PrimaryManager or the 公演代理人 may update), so
 * the save is no longer cut off by a 403 after the member changes.
 */
describe('メンバー管理: 参加者担当のみのユーザーの保存', () => {
  it('saves the member change, never calls the Production update, and shows the saved state', async () => {
    const calls = renderScreen(participantManagerOnly);

    await waitFor(() => expect(screen.getByTestId(`participant-row-${member.id}`)).toBeVisible(), FIRST_RENDER);
    expect(screen.queryByTestId('production-participants-published-date')).toBeNull();

    // The row's edit state is set up once the members have loaded.
    await waitFor(() => {
      fireEvent.press(screen.getByTestId(`participant-type-${member.id}-STAFF`));
      expect(screen.getByTestId(`participant-type-${member.id}-STAFF`).props.accessibilityState?.selected).toBe(true);
    }, FIRST_RENDER);
    fireEvent.press(screen.getByTestId('production-participants-save'));

    await waitFor(
      () => expect(calls.some((c) => c.method === 'PUT' && c.url.endsWith(`/participants/${member.id}`))).toBe(true),
      FIRST_RENDER
    );
    const participantUpdate = calls.find((c) => c.method === 'PUT' && c.url.endsWith(`/participants/${member.id}`));
    // R14-B (design doc §5.2 / §10, N6): the role and remarks only - `status`
    // is never sent by this save.
    expect(participantUpdate?.body).toEqual({ participant_type: 'STAFF', remarks: null });

    // The member list is fetched again after the save, and shows the saved type.
    await waitFor(
      () => expect(calls.filter((c) => c.method === 'GET' && c.url.endsWith('/productions/prod-1/participants')).length).toBeGreaterThan(1),
      FIRST_RENDER
    );
    await waitFor(
      () => expect(screen.getByTestId(`participant-type-${member.id}-STAFF`).props.accessibilityState?.selected).toBe(true),
      FIRST_RENDER
    );
    await waitFor(() => expect(screen.getByTestId('production-participants-save').props.accessibilityState?.disabled).toBeFalsy(), FIRST_RENDER);

    expect(calls.some((c) => c.method === 'PUT' && c.url.endsWith('/productions/prod-1'))).toBe(false);
    expect(screen.queryByText('forbidden')).toBeNull();
  });

  it('the PrimaryManager still sees メンバー情報公開日時 and saves it with the Production update', async () => {
    const calls = renderScreen({ ...productionOne, is_primary_manager: true, delegate_role: null, delegate_roles: [] });

    await waitFor(() => expect(screen.getByTestId(`participant-row-${member.id}`)).toBeVisible(), FIRST_RENDER);
    expect(screen.getByTestId('production-participants-published-date')).toBeVisible();

    fireEvent.press(screen.getByTestId('production-participants-save'));

    await waitFor(() => expect(calls.some((c) => c.method === 'PUT' && c.url.endsWith('/productions/prod-1'))).toBe(true), FIRST_RENDER);
  });
});
