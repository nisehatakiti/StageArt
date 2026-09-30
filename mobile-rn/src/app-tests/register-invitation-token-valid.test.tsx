import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * StageArt メール招待によるProductionParticipant追加機能 §23: arriving at
 * /register via an invitation link (?token=...) pre-fills the invited
 * email address from the unauthenticated
 * GET /participant-invitations/resolve preview, and shows a notice
 * naming the Production.
 */
describe('register screen: valid invitation token', () => {
  it('pre-fills the invited email and shows the production name when a valid token is present', async () => {
    mockFetchRoutes([
      {
        test: (u) => u.includes('/participant-invitations/resolve') && u.includes('token=good-token'),
        status: 200,
        body: {
          production_name: '踊れチュパカブラ',
          email: 'invitee@example.com',
          name: '山田 花子',
          participant_type: 'CAST',
          status: 'PENDING',
        },
      },
    ]);

    renderRouter('src/app', { initialUrl: '/register?token=good-token' });

    await waitFor(() => expect(screen.getByTestId('register-invitation-notice')).toBeVisible());
    expect(screen.getByText(/踊れチュパカブラ/)).toBeVisible();
    await waitFor(() => expect(screen.getByTestId('register-email').props.value).toBe('invitee@example.com'));
    await waitFor(() => expect(screen.getByTestId('register-invitation-name').props.value).toBe('山田 花子'));
  });
});
