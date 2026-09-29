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
 * StageArt メール招待によるProductionParticipant追加機能 §22: メールアドレス
 * で検索して既存Personが見つかった場合、既存の
 * POST /productions/{id}/participants (subject_type=PERSON) 経路でその
 * まま追加する。
 */
describe('Web メンバー管理: メールアドレス検索で既存メンバーを追加', () => {
  it('searches by email, previews the found person, and adds them as a PERSON participant', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne },
      { test: (u) => u.endsWith('/productions/prod-1/participants'), status: 200, body: [] },
      { test: (u) => u.endsWith('/productions/prod-1/participation-requests'), status: 200, body: [] },
      { test: (u) => u.endsWith('/productions/prod-1/participant-invitations'), status: 200, body: [] },
      {
        test: (u) => u.includes('/people?') && u.includes('email=alice%40example.com'),
        status: 200,
        body: { id: 'person-9', family_name: '鈴木', given_name: '花子' },
      },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/productions/prod-1/participants' });

    await waitFor(() => expect(screen.getByTestId('production-participants-email-input')).toBeVisible());

    fireEvent.changeText(screen.getByTestId('production-participants-email-input'), 'alice@example.com');
    await waitFor(() => expect(screen.getByTestId('production-participants-email-input').props.value).toBe('alice@example.com'));
    fireEvent.press(screen.getByTestId('production-participants-email-search'));

    await waitFor(() => expect(screen.getByTestId('production-participants-email-preview')).toBeVisible(), { timeout: 3000 });
    expect(screen.getByText('鈴木 花子')).toBeVisible();

    (global.fetch as jest.Mock).mockImplementationOnce(async () => ({
      ok: true,
      status: 201,
      text: async () =>
        JSON.stringify({
          id: 'participant-2',
          production_id: 'prod-1',
          subject_type: 'PERSON',
          subject_id: 'person-9',
          participant_type: 'CAST',
          status: 'ACTIVE',
          created_at: '',
          updated_at: '',
          remarks: null,
          display_name: null,
          person_family_name: '鈴木',
          person_given_name: '花子',
        }),
      json: async () => ({}),
    }));

    fireEvent.press(screen.getByTestId('production-participants-email-add-confirm'));

    await waitFor(() => {
      const call = (global.fetch as jest.Mock).mock.calls.find(
        ([url, init]: [string, RequestInit?]) => url.endsWith('/productions/prod-1/participants') && init?.method === 'POST'
      );
      expect(call).toBeDefined();
      const body = JSON.parse(call![1].body as string);
      expect(body.subject_type).toBe('PERSON');
      expect(body.subject_id).toBe('person-9');
    });
  });
});
