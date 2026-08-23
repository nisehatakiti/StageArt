import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * Kept in its own file (Jest gives every test file a fresh module
 * registry): expo-router/testing-library's renderRouter() leaves
 * module-level navigation state behind that persists across multiple
 * renderRouter() calls within a single file even after RNTL's
 * cleanup(), which made this test unreliable when co-located with
 * src/app/navigation.test.tsx's own renderRouter() call - and, per this
 * same established codebase convention, unreliable when co-located with
 * a second renderRouter() call in this very file (see
 * login-flow-error.test.tsx, split out for the same reason). A real
 * separate file sidesteps that Expo Router testing-library limitation
 * entirely rather than working around it.
 */
describe('login flow', () => {
  it('a successful Email+Password login navigates from /login to /home', async () => {
    mockFetchRoutes([
      {
        test: (u) => u.endsWith('/auth/email/login'),
        status: 200,
        body: {
          access_token: 'access-token-1',
          refresh_token: 'refresh-token-1',
          token_type: 'Bearer',
          expires_in: 3600,
          person_id: 'person-1',
          user_account_id: 'account-1',
          is_new_user: false,
        },
      },
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: { upcoming_rehearsals: [], notifications: [] } },
    ]);

    renderRouter('src/app', { initialUrl: '/login' });

    await waitFor(() => expect(screen.getByTestId('login-email')).toBeVisible());

    await fireEvent.changeText(screen.getByTestId('login-email'), 'person@example.com');
    await fireEvent.changeText(screen.getByTestId('login-password'), 'password123');
    await fireEvent.press(screen.getByTestId('login-submit'));

    await waitFor(() => expect(screen.getByTestId('home-primary-nav')).toBeVisible());
  });
});
