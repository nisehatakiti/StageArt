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
 * StageArt Productionメンバー追加(氏名＋メールアドレス統一)ラウンド §2-A/§19:
 * submitting 氏名＋メールアドレス for an email that already belongs to an
 * existing StageArt Person adds them directly as a Participant via the
 * single POST /productions/{id}/participant-invitations call - no
 * separate search step, no Invitation created for this outcome.
 */
describe('Web メンバー管理: 既存メンバーをメールアドレスで直接追加', () => {
  it('adds an existing Person directly when the email already belongs to one', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne },
      { test: (u) => u.endsWith('/productions/prod-1/participants'), status: 200, body: [] },
      { test: (u) => u.endsWith('/productions/prod-1/participation-requests'), status: 200, body: [] },
      { test: (u) => u.endsWith('/productions/prod-1/participant-invitations'), status: 200, body: [] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/productions/prod-1/participants' });

    await waitFor(() => expect(screen.getByTestId('production-participants-new-member-name')).toBeVisible());

    fireEvent.changeText(screen.getByTestId('production-participants-new-member-name'), '山田 太郎');
    fireEvent.changeText(screen.getByTestId('production-participants-new-member-email'), 'already-registered@example.com');
    await waitFor(() => expect(screen.getByTestId('production-participants-new-member-email').props.value).toBe('already-registered@example.com'));

    (global.fetch as jest.Mock).mockImplementationOnce(async () => ({
      ok: true,
      status: 201,
      text: async () =>
        JSON.stringify({
          outcome: 'PARTICIPANT_ADDED',
          participant: {
            id: 'participant-2',
            production_id: 'prod-1',
            subject_type: 'PERSON',
            subject_id: 'person-9',
            participant_type: 'CAST',
            status: 'ACTIVE',
            created_at: '',
            updated_at: '',
            remarks: null,
            display_name: '山田 太郎',
            person_family_name: '鈴木',
            person_given_name: '花子',
          },
          invitation: null,
        }),
      json: async () => ({}),
    }));

    fireEvent.press(screen.getByTestId('production-participants-add-member'));

    await waitFor(() => {
      const call = (global.fetch as jest.Mock).mock.calls.find(
        ([url, init]: [string, RequestInit?]) => url.endsWith('/productions/prod-1/participant-invitations') && init?.method === 'POST'
      );
      expect(call).toBeDefined();
      const body = JSON.parse(call![1].body as string);
      expect(body.name).toBe('山田 太郎');
      expect(body.email).toBe('already-registered@example.com');
      expect(body.participant_type).toBe('CAST');
    });

    await waitFor(() => expect(screen.getByText('メンバーを追加しました。')).toBeVisible());
  });
});
