import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty, productionOne } from './__fixtures__/homeFixtures';
import { participantRow } from './__fixtures__/r14bFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

const delegate = (id: string, effective: boolean, reason: string | null, status = 'ACTIVE') => ({
  id,
  production_id: 'prod-1',
  person_id: `person-${id}`,
  person_family_name: '担当',
  person_given_name: id,
  role: 'REHEARSAL_MANAGER',
  status,
  created_by: 'person-1',
  created_at: '',
  updated_by: 'person-1',
  updated_at: '',
  effective,
  ineffective_reason: reason,
});

/**
 * PR #7 R14-B stage 9 (design doc §4.6 / §10 / §14.2): the delegates screen
 * lists effective and ineffective delegates apart, says why a role grants
 * nothing (同意未確認 / メンバーではない / 解除済み) and leads to member
 * management for the next step; there is no Person ID input.
 */
describe('R14-B 担当者: 有効・無効の区別', () => {
  it('splits the delegates and shows the reasons with the way to member management', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne },
      {
        test: (u) => u.endsWith('/productions/prod-1/delegates'),
        status: 200,
        body: [
          delegate('a', true, null),
          delegate('b', false, 'CONSENT_UNCONFIRMED'),
          delegate('c', false, 'NOT_ACTIVE_PARTICIPANT'),
          delegate('d', false, 'INACTIVE', 'INACTIVE'),
        ],
      },
      { test: (u) => u.endsWith('/productions/prod-1/participants'), status: 200, body: [participantRow()] },
      { test: (u) => u.endsWith('/productions/prod-1/participation-requests'), status: 200, body: [] },
      { test: (u) => u.endsWith('/productions/prod-1/participant-invitations'), status: 200, body: [] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/productions/prod-1/delegates' });

    await waitFor(() => expect(screen.getByTestId('production-delegates-effective')).toBeVisible());
    expect(screen.getByText('有効な担当者')).toBeVisible();
    expect(screen.getByText('無効な担当者')).toBeVisible();
    expect(screen.queryByTestId('production-delegate-reason-a')).toBeNull();
    expect(screen.getByTestId('production-delegate-reason-b')).toHaveTextContent(/同意未確認のため無効です。本人に同意の確認を依頼してください/);
    expect(screen.getByTestId('production-delegate-reason-c')).toHaveTextContent(/この公演のメンバーではないため無効です。招待して、承諾後に改めて任命してください/);
    expect(screen.getByTestId('production-delegate-reason-d')).toHaveTextContent(/解除済み/);
    expect(screen.queryByTestId('production-delegate-to-members-d')).toBeNull();
    expect(screen.queryByTestId('production-delegates-new-person-id')).toBeNull();

    fireEvent.press(screen.getByTestId('production-delegate-to-members-b'));
    await waitFor(() => expect(screen.getByTestId('production-participants-list')).toBeVisible());
  });
});
