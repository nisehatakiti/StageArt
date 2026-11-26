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
 * StageArt Production側氏名の権威付けラウンド AC-09: when a PERSON
 * Participant carries its own Production-specific `display_name`, the
 * member list must show that - never the linked Person's own
 * family_name/given_name, even when the two differ.
 */
describe('Web メンバー管理: Production側氏名の優先表示', () => {
  it('shows the Participant display_name instead of the Person real name when they differ', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne },
      { test: (u) => u.endsWith('/productions/prod-1/participants'), status: 200, body: [
        {
          id: 'participant-1',
          production_id: 'prod-1',
          subject_type: 'PERSON',
          subject_id: 'person-9',
          participant_type: 'CAST',
          status: 'ACTIVE',
          created_at: '',
          updated_at: '',
          remarks: '主演',
          display_name: '佐藤一郎（劇団いるか）',
          person_family_name: '佐藤',
          person_given_name: '一郎',
        },
      ] },
      { test: (u) => u.endsWith('/productions/prod-1/participation-requests'), status: 200, body: [] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/productions/prod-1/participants' });

    await waitFor(() => expect(screen.getByTestId('participant-row-participant-1')).toBeVisible());
    const row = within(screen.getByTestId('participant-row-participant-1'));
    expect(row.getByText('佐藤一郎（劇団いるか）')).toBeVisible();
    expect(row.queryByText('佐藤 一郎')).toBeNull();
  });
});
