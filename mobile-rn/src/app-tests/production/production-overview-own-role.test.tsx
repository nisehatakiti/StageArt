import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, productionOne } from './__fixtures__/productionShellFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * PR #7 R14-B stage 9 (design doc §10 「公演の画面（担当者本人）」; API
 * GET /productions/{id}/delegates/me added with the user's approval): a
 * role of the person's own that grants nothing is explained on the
 * participant-facing overview; a released (INACTIVE) role is not.
 */
describe('公演概要ダッシュボード: 自分の担当者の役割が無効な理由', () => {
  it('explains an ineffective own role', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne },
      { test: (u) => u.endsWith('/productions/prod-1/overview'), status: 200, body: productionOne },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: { upcoming_rehearsals: [], notifications: [], followed_organizations_feed: [] } },
      { test: (u) => u.endsWith('/me/participating-productions'), status: 200, body: [] },
      {
        test: (u) => u.endsWith('/productions/prod-1/delegates/me'),
        status: 200,
        body: [
          { id: 'd-1', production_id: 'prod-1', role: 'REHEARSAL_MANAGER', status: 'ACTIVE', effective: false, ineffective_reason: 'NOT_ACTIVE_PARTICIPANT' },
          { id: 'd-2', production_id: 'prod-1', role: 'TICKET_MANAGER', status: 'INACTIVE', effective: false, ineffective_reason: 'INACTIVE' },
        ],
      },
    ]);

    renderRouter('src/app', { initialUrl: '/production/prod-1/overview' });

    await waitFor(() => expect(screen.getByTestId('production-overview-own-role-ineffective')).toBeVisible());
    // Invitation policy 2026-11-27: the only reason left for an ACTIVE row is not being a member (no consent any more).
    expect(screen.getByTestId('production-overview-own-role-NOT_ACTIVE_PARTICIPANT')).toHaveTextContent(/この公演のメンバーではないため無効/);
    expect(screen.queryByText(/同意/)).toBeNull();
    expect(screen.queryByTestId('production-overview-own-role-INACTIVE')).toBeNull();
  });
});
