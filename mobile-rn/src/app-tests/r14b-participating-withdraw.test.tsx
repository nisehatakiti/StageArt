import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty, productionOne } from './__fixtures__/homeFixtures';
import { apiAnswer, callsTo } from './__fixtures__/r14bFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

const participation = (n: number) => ({
  participant_id: `participant-${n}`,
  production_id: `prod-${n}`,
  production_name: `公演${n}`,
  production_slug: null,
  participant_type: 'CAST',
});

/**
 * PR #7 R14-B stage 9 (design doc §7.2 / §10, D9, §14.2):
 * - not offered on a Production the person is the PrimaryManager of;
 * - the confirmation says delegate roles end too when the person holds one;
 * - (409 withdraw_not_allowed: r14b-participating-withdraw-not-allowed.test.tsx)
 * - a success sends POST /participants/{id}/withdraw and refreshes the list.
 */
describe('R14-B 参加公演: 参加をやめる', () => {
  it('confirms, explains the delegate roles and withdraws', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/me/participating-productions'), status: 200, body: [participation(1), participation(2), participation(3)] },
      {
        test: (u) => u.endsWith('/productions'),
        status: 200,
        body: [
          { ...productionOne, id: 'prod-1', is_primary_manager: true, delegate_roles: [] },
          { ...productionOne, id: 'prod-2', is_primary_manager: false, delegate_roles: ['REHEARSAL_MANAGER'] },
        ],
      },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/participating-productions' });

    await waitFor(() => expect(screen.getByTestId('participating-production-primary-manager-prod-1')).toBeVisible());
    expect(screen.queryByTestId('participating-production-withdraw-prod-1')).toBeNull();

    // A delegate: the confirmation says the roles end too; withdrawing succeeds and the list is refetched.
    await fireEvent.press(screen.getByTestId('participating-production-withdraw-prod-2'));
    await waitFor(() => expect(screen.getByTestId('participating-production-confirm-prod-2')).toBeVisible());
    expect(screen.getByTestId('participating-production-delegate-note-prod-2')).toHaveTextContent('担当者の役割も解除されます。');

    (global.fetch as jest.Mock).mockImplementationOnce(apiAnswer(200, { participant_id: 'participant-2', production_id: 'prod-2', status: 'WITHDRAWN' }));
    await fireEvent.press(screen.getByTestId('participating-production-withdraw-confirm-prod-2'));
    await waitFor(() => expect(screen.getByTestId('participating-productions-withdrawn')).toHaveTextContent('「公演2」の参加をやめました。'));
    expect(callsTo('/participants/participant-2/withdraw', 'POST')).toHaveLength(1);
    await waitFor(() => expect(callsTo('/me/participating-productions', 'GET').length).toBeGreaterThan(1));
  });
});
