import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty, productionOne } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

const pendingInvitation = {
  id: 'invitation-1',
  production_id: 'prod-1',
  email: 'invitee@example.com',
  name: '山田 花子',
  invited_by_person_id: 'person-1',
  participant_type: 'CAST',
  remarks: null,
  status: 'PENDING',
  created_at: '',
  expires_at: '2099-01-01T00:00:00+00:00',
  consumed_at: null,
  is_expired: false,
  // R14B-4R (§7.1): the managers' list fields.
  effective_status: 'PENDING',
  responded_at: null,
  close_reason: null,
  closed_at: null,
  resend_count: 0,
  resend_remaining: 5,
  send_status: 'SENT',
  last_sent_at: '2026-11-18T01:00:00+00:00',
  send_limit: { sent_in_30_days: 1, remaining_in_30_days: 5, available_at: null },
};

/**
 * StageArt メール招待によるProductionParticipant追加機能 §24: メンバー管理
 * 画面から現在のPENDING Invitationを一覧・再送・取消できる。
 */
describe('Web メンバー管理: 招待一覧・再送・取消', () => {
  it('lists a pending invitation and can resend and cancel it', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne },
      { test: (u) => u.endsWith('/productions/prod-1/participants'), status: 200, body: [] },
      { test: (u) => u.endsWith('/productions/prod-1/participation-requests'), status: 200, body: [] },
      { test: (u) => u.endsWith('/productions/prod-1/participant-invitations'), status: 200, body: [pendingInvitation] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/productions/prod-1/participants' });

    await waitFor(() => expect(screen.getByTestId('participant-invitation-row-invitation-1')).toBeVisible());
    expect(screen.getByText('invitee@example.com')).toBeVisible();
    expect(screen.getByText('山田 花子')).toBeVisible();
    // R14-B §10: the Backend's effective_status as the state.
    expect(screen.getByTestId('participant-invitation-status-invitation-1')).toHaveTextContent('登録待ち');

    (global.fetch as jest.Mock).mockImplementationOnce(async () => ({
      ok: true,
      status: 200,
      text: async () => JSON.stringify({ ...pendingInvitation, expires_at: '2099-02-01T00:00:00+00:00' }),
      json: async () => ({}),
    }));
    fireEvent.press(screen.getByTestId('participant-invitation-resend-invitation-1'));

    await waitFor(() => {
      const call = (global.fetch as jest.Mock).mock.calls.find(
        ([url, init]: [string, RequestInit?]) => url.endsWith('/participant-invitations/invitation-1/resend') && init?.method === 'POST'
      );
      expect(call).toBeDefined();
    });

    (global.fetch as jest.Mock).mockImplementationOnce(async () => ({
      ok: true,
      status: 200,
      text: async () => JSON.stringify({ ...pendingInvitation, status: 'CANCELLED' }),
      json: async () => ({}),
    }));
    fireEvent.press(screen.getByTestId('participant-invitation-cancel-invitation-1'));

    await waitFor(() => {
      const call = (global.fetch as jest.Mock).mock.calls.find(
        ([url, init]: [string, RequestInit?]) => url.endsWith('/participant-invitations/invitation-1/cancel') && init?.method === 'POST'
      );
      expect(call).toBeDefined();
    });
  });
});
