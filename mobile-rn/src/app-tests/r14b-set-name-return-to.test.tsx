import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

/**
 * PR #7 R14-B (design doc §10 「ログイン」, §14.2, R13 Medium; approved fix A):
 * after the name is saved, set-name.tsx goes to its return_to only when it
 * is an in-app path (login hands over /participant-invitations; Profile
 * hands over /profile); anything else falls back to /home. Rendered
 * directly with its router / API mocked.
 */
const mockReplace = jest.fn();
let mockParams: Record<string, string | undefined> = {};
const mockUpdatePersonName = jest.fn(async () => undefined);

jest.mock('expo-router', () => ({
  useRouter: () => ({ replace: mockReplace, push: jest.fn() }),
  useLocalSearchParams: () => mockParams,
  Redirect: () => null,
}));
jest.mock('@/auth/AuthContext', () => ({ useAuth: () => ({ apiClient: {} }) }));
jest.mock('@/hooks/use-site-host', () => ({ useIsPublicSite: () => false }));
jest.mock('@tanstack/react-query', () => ({ useQueryClient: () => ({ invalidateQueries: async () => undefined }) }));
jest.mock('@/features/auth/api', () => ({ updatePersonName: () => mockUpdatePersonName() }));

async function saveNameWith(returnTo: string | undefined): Promise<unknown> {
  mockParams = returnTo === undefined ? {} : { return_to: returnTo };
  mockReplace.mockClear();
  // eslint-disable-next-line @typescript-eslint/no-require-imports
  const SetNameScreen = require('@/app/set-name').default;
  await render(<SetNameScreen />);
  await fireEvent.changeText(screen.getByTestId('set-name-family-name'), '舞台');
  await fireEvent.changeText(screen.getByTestId('set-name-given-name'), '花子');
  await fireEvent.press(screen.getByTestId('set-name-submit'));
  await waitFor(() => expect(mockReplace).toHaveBeenCalled());
  return mockReplace.mock.calls[0][0];
}

describe('set-name: return_to', () => {
  it('continues to the invitation list handed over by login', async () => {
    expect(await saveNameWith('/participant-invitations')).toBe('/participant-invitations');
  });

  it('keeps Profile\'s /profile', async () => {
    expect(await saveNameWith('/profile')).toBe('/profile');
  });

  it.each([
    ['another site', 'https://evil.example.com/'],
    ['protocol-relative', '//evil.example.com'],
    ['a scheme', 'javascript:alert(1)'],
    ['an auth screen', '/login'],
  ])('falls back to /home for %s', async (_label, value) => {
    expect(await saveNameWith(value)).toBe('/home');
  });

  it('goes to /home without return_to', async () => {
    expect(await saveNameWith(undefined)).toBe('/home');
  });
});
