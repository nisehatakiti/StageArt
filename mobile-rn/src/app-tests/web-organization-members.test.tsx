import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty, orgOne } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * StageArt Web版 団体管理 Phase: メンバー管理. No Backend Use Case lists an
 * Organization's active Members (confirmed by reading every
 * Application/Membership/* class - see members.tsx's own docblock), so
 * this only ever asserts what IS real: the current Person's own Role,
 * and (for an Owner) working links to 参加申請/招待 - never a fabricated
 * roster.
 */
describe('Web メンバー管理', () => {
  it('shows the current Person’s own Role and links to 参加申請/招待 for an Owner, with no fabricated member list', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [orgOne] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/organizations/org-1/members' });

    await waitFor(() => expect(screen.getByTestId('organization-members-self-role')).toBeVisible());
    expect(screen.getByText('オーナー')).toBeVisible();
    expect(screen.getByTestId('organization-members-list-unavailable')).toBeVisible();
    expect(screen.getByTestId('organization-members-link-requests')).toBeVisible();
    expect(screen.getByTestId('organization-members-link-invite')).toBeVisible();
  });
});
