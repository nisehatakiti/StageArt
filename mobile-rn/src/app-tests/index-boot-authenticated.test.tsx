import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * Kept in its own file - see login-flow.test.tsx's docblock for why
 * every renderRouter() call needs a dedicated file in this codebase.
 *
 * A real-device correction removed index.tsx's earlier GET /me +
 * email_verified check entirely (see that screen's own docblock): a
 * stored, refreshable session now always means /home, full stop - there
 * is no "authenticated but unverified" state left to distinguish, since
 * AuthContext never persists a session for an unverified Email+Password
 * account in the first place (see AuthContext.tsx's registerWithEmail/
 * loginWithEmail docblocks). This replaces the older, now-deleted
 * index-email-verification-gate.test.tsx (which exercised a state that
 * can no longer occur) and index-email-verification-gate-verified.test.tsx.
 */
describe('index.tsx: boot with a stored session', () => {
  it('redirects straight to /home once the Refresh Token exchange succeeds', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/' });

    await waitFor(() => expect(screen.getByTestId('home-primary-nav')).toBeVisible());
  });
});
