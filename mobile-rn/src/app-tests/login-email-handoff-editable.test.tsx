import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

import { AuthProvider } from '@/auth/AuthContext';
import { clearLoginEmailHandoff, peekLoginEmailHandoff, setLoginEmailHandoff } from '@/auth/loginEmailHandoff';

import LoginScreen from '../app/login';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

const mockRouter = { push: jest.fn(), replace: jest.fn(), setParams: jest.fn(), back: jest.fn() };

jest.mock('expo-router', () => ({
  ...jest.requireActual('expo-router'),
  useRouter: () => mockRouter,
}));

/**
 * The login screen on its own (the renderRouter() tests do not deliver
 * text input events): a handed-over address pre-fills the e-mail field,
 * stays editable, and is read only once; without one the field is empty.
 */
describe('login screen: e-mail handed over from the confirmation screen', () => {
  beforeEach(() => {
    global.fetch = jest.fn();
    clearLoginEmailHandoff();
  });

  it('pre-fills the handed-over address, lets the user change it, and clears the hand-over', async () => {
    setLoginEmailHandoff('member@example.com');

    render(
      <AuthProvider>
        <LoginScreen />
      </AuthProvider>
    );

    await waitFor(() => expect(screen.getByTestId('login-email').props.value).toBe('member@example.com'));
    expect(peekLoginEmailHandoff()).toBeNull();

    fireEvent.changeText(screen.getByTestId('login-email'), 'other@example.com');
    await waitFor(() => expect(screen.getByTestId('login-email').props.value).toBe('other@example.com'));
  });

  it('starts empty when nothing was handed over (the ordinary login screen)', async () => {
    render(
      <AuthProvider>
        <LoginScreen />
      </AuthProvider>
    );

    await waitFor(() => expect(screen.getByTestId('login-email')).toBeVisible());
    expect(screen.getByTestId('login-email').props.value).toBe('');
  });
});
