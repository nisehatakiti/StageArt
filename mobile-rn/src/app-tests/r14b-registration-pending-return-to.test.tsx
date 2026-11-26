import { fireEvent, render, screen } from '@testing-library/react-native';

/**
 * Two-step login (R14-B §10 return_to): registration-pending was opened
 * with the login screen's return_to (an invited person who registered or
 * whose address is not confirmed yet). Both ways back to login keep the
 * checked return_to, so after confirming the address and logging in the
 * person reaches the invitation list; anything that is not an in-app path
 * is dropped. Rendered on its own with the router mocked (one state-free
 * press each; the real-router version is registration-pending-*.test.tsx).
 */
const mockReplace = jest.fn();
let mockParams: Record<string, string | undefined> = {};

jest.mock('expo-router', () => ({
  useRouter: () => ({ replace: mockReplace, push: jest.fn(), back: jest.fn() }),
  useLocalSearchParams: () => mockParams,
  Redirect: () => null,
}));
jest.mock('@/hooks/use-site-host', () => ({ useIsPublicSite: () => false }));

// eslint-disable-next-line @typescript-eslint/no-require-imports
const RegistrationPendingScreen = require('@/app/registration-pending').default;

beforeEach(() => {
  mockReplace.mockClear();
});

describe('registration-pending: return_to back to login', () => {
  it.each([['registration-pending-back-to-login'], ['registration-pending-wrong-email']])('%s keeps a safe return_to', async (testID) => {
    mockParams = { email: 'invitee@example.com', return_to: '/participant-invitations' };
    await render(<RegistrationPendingScreen />);
    await fireEvent.press(screen.getByTestId(testID));
    expect(mockReplace).toHaveBeenCalledWith('/login?return_to=%2Fparticipant-invitations');
  });

  it.each([
    ['another site', 'https://evil.example.com/'],
    ['protocol-relative', '//evil.example.com'],
    ['an auth screen', '/register'],
    ['missing', undefined],
  ])('drops a return_to that is %s', async (_label, value) => {
    mockParams = { email: 'invitee@example.com', return_to: value };
    await render(<RegistrationPendingScreen />);
    await fireEvent.press(screen.getByTestId('registration-pending-back-to-login'));
    expect(mockReplace).toHaveBeenCalledWith('/login');
  });
});
