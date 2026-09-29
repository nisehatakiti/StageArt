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
 * StageArt メール招待によるProductionParticipant追加機能 §16/§22: メール
 * アドレス検索でStageArt未登録と分かった場合、
 * POST /productions/{id}/participant-invitations で招待メールを送信する。
 */
describe('Web メンバー管理: 未登録メールアドレスへの招待送信', () => {
  it('shows a not-found preview and sends an invitation when the email has no existing Person', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne },
      { test: (u) => u.endsWith('/productions/prod-1/participants'), status: 200, body: [] },
      { test: (u) => u.endsWith('/productions/prod-1/participation-requests'), status: 200, body: [] },
      { test: (u) => u.endsWith('/productions/prod-1/participant-invitations'), status: 200, body: [] },
      {
        test: (u) => u.includes('/people?') && u.includes('email=unknown%40example.com'),
        status: 404,
        body: { code: 'stageart_person_not_found', message: 'not found' },
      },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/productions/prod-1/participants' });

    await waitFor(() => expect(screen.getByTestId('production-participants-email-input')).toBeVisible());

    fireEvent.changeText(screen.getByTestId('production-participants-email-input'), 'unknown@example.com');
    await waitFor(() => expect(screen.getByTestId('production-participants-email-input').props.value).toBe('unknown@example.com'));
    fireEvent.press(screen.getByTestId('production-participants-email-search'));

    await waitFor(() => expect(screen.getByTestId('production-participants-email-not-found')).toBeVisible(), { timeout: 3000 });

    (global.fetch as jest.Mock).mockImplementationOnce(async () => ({
      ok: true,
      status: 201,
      text: async () =>
        JSON.stringify({
          outcome: 'INVITATION_CREATED',
          participant: null,
          invitation: {
            id: 'invitation-1',
            production_id: 'prod-1',
            email: 'unknown@example.com',
            invited_by_person_id: 'person-1',
            participant_type: 'CAST',
            remarks: null,
            status: 'PENDING',
            created_at: '',
            expires_at: '2099-01-01T00:00:00+00:00',
            consumed_at: null,
            is_expired: false,
          },
        }),
      json: async () => ({}),
    }));

    fireEvent.press(screen.getByTestId('production-participants-email-invite-confirm'));

    await waitFor(() => {
      const call = (global.fetch as jest.Mock).mock.calls.find(
        ([url, init]: [string, RequestInit?]) => url.endsWith('/productions/prod-1/participant-invitations') && init?.method === 'POST'
      );
      expect(call).toBeDefined();
      const body = JSON.parse(call![1].body as string);
      expect(body.email).toBe('unknown@example.com');
      expect(body.participant_type).toBe('CAST');
    });

    await waitFor(() => expect(screen.getByTestId('production-participants-invitation-message')).toBeVisible());
  });
});
