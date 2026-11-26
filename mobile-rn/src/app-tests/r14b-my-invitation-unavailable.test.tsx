import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty } from './__fixtures__/homeFixtures';
import { myInvitation } from './__fixtures__/r14bFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * PR #7 R14-B stage 9 (design doc §10, U7, N5, §14.2): can_accept=false
 * shows 「この招待は現在承諾できません」 and only 「参加しない」; an id not
 * in the own list shows no details, only the common notice and the way
 * back (expired / answered / not theirs / unknown are not told apart).
 */
describe('R14-B 招待確認: 承諾できない・表示できない', () => {
  it('offers only decline for an invitation that cannot be accepted', async () => {
    mockFetchRoutes([
      {
        test: (u) => u.endsWith('/me/participant-invitations'),
        status: 200,
        body: [myInvitation({ can_accept: false, unavailable_reason: 'INVITER_REVOKED', effective_status: 'INVITER_REVOKED' })],
      },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/participant-invitations/invitation-1' });

    await waitFor(() => expect(screen.getByTestId('participant-invitation-cannot-accept')).toBeVisible());
    expect(screen.getByTestId('participant-invitation-cannot-accept')).toHaveTextContent(/この招待は現在承諾できません/);
    expect(screen.queryByTestId('participant-invitation-accept')).toBeNull();
    expect(screen.getByTestId('participant-invitation-reject')).toBeVisible();
    expect(screen.queryByTestId('participant-invitation-sharing')).toBeNull();
  });
});
