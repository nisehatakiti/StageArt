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
 * StageArt メンバー管理画面改善 §1 instruction: a PERSON participant who is
 * not the viewer must show their resolved real name (person_family_name/
 * person_given_name, from ParticipantResult.php's new Person resolution),
 * never a raw Person ID.
 */
describe('Web メンバー管理: PERSON参加者の実名表示', () => {
  it('shows another PERSON participant by their resolved real name, not a raw Person ID', async () => {
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
        },
      ] },
      { test: (u) => u.endsWith('/productions/prod-1/participation-requests'), status: 200, body: [] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/productions/prod-1/participants' });

    await waitFor(() => expect(screen.getByTestId('participant-row-participant-1')).toBeVisible());
    const row = within(screen.getByTestId('participant-row-participant-1'));
    expect(row.getByText('鈴木 花子')).toBeVisible();
    expect(row.queryByText(/Person ID/)).toBeNull();
  });
});
