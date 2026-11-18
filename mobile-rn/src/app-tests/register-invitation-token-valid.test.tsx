import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * PR #7 R14-B (design doc §9.2 / §10 / §14.2, U5 C案; rewritten for the spec
 * change - previously a valid token pre-filled the invited address from
 * GET /participant-invitations/resolve, an API that no longer exists): an
 * old invitation link `/register?token=...` opens the ordinary registration
 * screen. The token is not read or looked up (no request is made with it),
 * it is removed from the URL at once, the address field starts empty and
 * editable, and nothing about any invitation is shown.
 */
describe('register screen: old invitation link with a token', () => {
  it('ignores the token, removes it from the URL and shows the ordinary empty form', async () => {
    mockFetchRoutes([]);

    const router = renderRouter('src/app', { initialUrl: '/register?token=good-token' });

    await waitFor(() => expect(screen.getByTestId('register-email')).toBeVisible());
    await waitFor(() => expect(router.getSearchParams()).toEqual({}));
    expect(router.getPathname()).toBe('/register');

    expect(screen.getByTestId('register-email').props.value).toBe('');
    expect(screen.getByTestId('register-email').props.editable).not.toBe(false);
    expect(screen.queryByTestId('register-invitation-notice')).toBeNull();
    expect(screen.queryByTestId('register-invitation-name')).toBeNull();

    const calls = (global.fetch as jest.Mock).mock.calls.map(([url]) => String(url));
    expect(calls.filter((url) => url.includes('good-token'))).toEqual([]);
    expect(calls.filter((url) => url.includes('/participant-invitations'))).toEqual([]);
  });
});
