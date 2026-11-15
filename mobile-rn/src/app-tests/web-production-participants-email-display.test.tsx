import { renderRouter, screen, waitFor, within } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty, productionOne } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * StageArt メンバー一覧メールアドレス表示ラウンド: the member table's
 * メールアドレス column shows the server-resolved `email` field (via the
 * existing PersonEmailResolver, see ParticipantResult.php's own
 * docblock) for a PERSON participant, and stays blank - never a crash,
 * never a placeholder string - when the Backend resolved no address.
 */
describe('Web メンバー管理: 登録済みメンバーのメールアドレス表示', () => {
  it('shows the resolved email for a PERSON participant, and blank when none was resolved', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne },
      { test: (u) => u.endsWith('/productions/prod-1/participants'), status: 200, body: [
        {
          id: 'participant-1',
          production_id: 'prod-1',
          subject_type: 'PERSON',
          subject_id: 'person-9',
          participant_type: 'STAFF',
          status: 'ACTIVE',
          created_at: '',
          updated_at: '',
          remarks: null,
          display_name: null,
          person_family_name: '鈴木',
          person_given_name: '花子',
          email: 'suzuki-hanako@example.com',
        },
        {
          id: 'participant-2',
          production_id: 'prod-1',
          subject_type: 'PERSON',
          subject_id: 'person-10',
          participant_type: 'CAST',
          status: 'ACTIVE',
          created_at: '',
          updated_at: '',
          remarks: null,
          display_name: null,
          person_family_name: '田中',
          person_given_name: '次郎',
          email: null,
        },
      ] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/productions/prod-1/participants' });

    await waitFor(() => expect(screen.getByTestId('participant-row-participant-1')).toBeVisible());
    const rowWithEmail = within(screen.getByTestId('participant-row-participant-1'));
    expect(rowWithEmail.getByTestId('participant-email-participant-1')).toHaveTextContent('suzuki-hanako@example.com');

    const rowWithoutEmail = within(screen.getByTestId('participant-row-participant-2'));
    expect(rowWithoutEmail.getByTestId('participant-email-participant-2')).toHaveTextContent('');
  });
});
