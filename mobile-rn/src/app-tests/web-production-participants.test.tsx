import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty, productionOne } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * StageArt Web版 公演管理 Phase: 出演者・参加者. Verifies both real,
 * independently-backed sections: 参加申請 (real person name, via the
 * already-existing usePendingParticipationRequests/
 * useParticipationRequestDecision hooks) and 現在の参加者 (the new
 * features/participant/ layer this Phase adds - GET
 * /productions/{id}/participants, ACTIVE only, self-labelled "あなた"
 * since Participant carries no resolved name for a PERSON subject).
 */
describe('Web 出演者・参加者', () => {
  it('lists a pending participation request with a real name and the current ACTIVE participant, and approving calls the real endpoint', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1/participation-requests'), status: 200, body: [
        {
          id: 'part-req-1',
          production_id: 'prod-1',
          person_id: 'person-9',
          person_family_name: '鈴木',
          person_given_name: '花子',
          participant_type: 'CAST',
          status: 'PENDING',
          requested_at: '2026-08-20T10:00:00+09:00',
        },
      ] },
      { test: (u) => u.endsWith('/participation-requests/part-req-1/approve'), status: 200, body: {} },
      { test: (u) => u.endsWith('/productions/prod-1/participants'), status: 200, body: [
        {
          id: 'participant-1',
          production_id: 'prod-1',
          subject_type: 'PERSON',
          subject_id: 'person-1',
          participant_type: 'STAFF',
          status: 'ACTIVE',
          created_at: '',
          updated_at: '',
        },
      ] },
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/productions/prod-1/participants' });

    await waitFor(() => expect(screen.getByTestId('participation-request-row-part-req-1')).toBeVisible());
    expect(screen.getByText('鈴木 花子')).toBeVisible();

    await waitFor(() => expect(screen.getByTestId('participant-row-participant-1')).toBeVisible());
    // person-1 matches DEFAULT_ROUTES' /me fixture id - the current Person.
    expect(screen.getByText('あなた')).toBeVisible();

    fireEvent.press(screen.getByTestId('participation-request-approve-part-req-1'));

    await waitFor(() => {
      const approveCall = (global.fetch as jest.Mock).mock.calls.find(
        ([url, options]: [string, RequestInit]) => url.includes('/participation-requests/part-req-1/approve') && options?.method === 'POST'
      );
      expect(approveCall).toBeDefined();
    });
  });
});
