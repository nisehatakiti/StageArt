import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

import { AuthProvider } from '@/auth/AuthContext';
import { clearEmailHandoff, peekEmailHandoff, setEmailHandoff } from '@/auth/loginEmailHandoff';

import RegisterScreen from '../app/register';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

const mockRouter = { push: jest.fn(), replace: jest.fn(), setParams: jest.fn(), back: jest.fn() };

jest.mock('expo-router', () => ({
  ...jest.requireActual('expo-router'),
  useRouter: () => mockRouter,
  useLocalSearchParams: () => ({}),
}));

/** The login screen's 「新規登録へ」 hands the address to the registration screen (memory only). */
describe('registration screen: address handed over from the login screen', () => {
  beforeEach(() => {
    global.fetch = jest.fn();
    clearEmailHandoff('register');
  });

  it('pre-fills the handed-over address, keeps it editable and reads it only once', async () => {
    setEmailHandoff('register', 'nobody@example.com');

    render(
      <AuthProvider>
        <RegisterScreen />
      </AuthProvider>
    );

    await waitFor(() => expect(screen.getByTestId('register-email').props.value).toBe('nobody@example.com'));
    expect(screen.getByTestId('register-email').props.editable).not.toBe(false);
    expect(peekEmailHandoff('register')).toBeNull();

    fireEvent.changeText(screen.getByTestId('register-email'), 'someone@example.com');
    await waitFor(() => expect(screen.getByTestId('register-email').props.value).toBe('someone@example.com'));
    expect(global.fetch).not.toHaveBeenCalled();
  });

  it('starts empty without a hand-over (a direct visit)', async () => {
    render(
      <AuthProvider>
        <RegisterScreen />
      </AuthProvider>
    );

    await waitFor(() => expect(screen.getByTestId('register-email')).toBeVisible());
    expect(screen.getByTestId('register-email').props.value).toBe('');
  });
});
