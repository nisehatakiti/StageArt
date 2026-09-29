import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

describe('register screen: invalid invitation token', () => {
  it('falls back to a normal, blank registration form when the token is invalid', async () => {
    mockFetchRoutes([
      {
        test: (u) => u.includes('/participant-invitations/resolve'),
        status: 404,
        body: { code: 'stageart_participant_invitation_not_found', message: 'not found' },
      },
    ]);

    renderRouter('src/app', { initialUrl: '/register?token=bad-token' });

    await waitFor(() => expect(screen.getByTestId('register-email')).toBeVisible());
    expect(screen.getByTestId('register-email').props.value).toBe('');
    expect(screen.queryByTestId('register-invitation-notice')).toBeNull();
  });
});
