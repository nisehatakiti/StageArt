import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * Kept in its own file - see login-flow.test.tsx's docblock for why
 * every renderRouter() call needs a dedicated file in this codebase.
 *
 * A real-device correction: reaching this screen no longer implies a
 * real, persisted StageArt session (see registration-pending.tsx's own
 * docblock) - SecureStore is mocked to return no stored tokens here,
 * matching that. The resend button's actual network call is covered
 * directly against requestEmailVerification() in
 * src/features/auth/api.test.ts (same established pattern as
 * mypage-account-linking-render.test.tsx's docblock explains for the
 * identical function). The "no transient token available" fallback
 * branch (a single `if (!token)` check in handleResend) is not
 * separately exercised via a press interaction here - it hit the same
 * renderRouter() local-state-press limitation already documented
 * elsewhere in this codebase (see verify-email-flow-error.test.tsx's
 * own docblock for the identical class of issue with its retry
 * button); the branch is simple and directly reviewable in
 * registration-pending.tsx itself.
 */
describe('registration-pending screen', () => {
  it('shows the registered email and offers a resend action', async () => {
    renderRouter('src/app', { initialUrl: '/registration-pending?email=person%40example.com' });

    await waitFor(() => expect(screen.getByTestId('registration-pending-email')).toHaveTextContent('person@example.com'));
    expect(screen.getByTestId('registration-pending-resend')).toBeVisible();
  });
});
