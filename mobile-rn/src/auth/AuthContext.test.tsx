import { act, renderHook, waitFor } from '@testing-library/react-native';
import type { PropsWithChildren } from 'react';

import { AuthProvider, useAuth } from './AuthContext';

const mockSecureStore = new Map<string, string>();

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) => mockSecureStore.get(key) ?? null),
  setItemAsync: jest.fn(async (key: string, value: string) => {
    mockSecureStore.set(key, value);
  }),
  deleteItemAsync: jest.fn(async (key: string) => {
    mockSecureStore.delete(key);
  }),
}));

function wrapper({ children }: PropsWithChildren) {
  return <AuthProvider>{children}</AuthProvider>;
}

function mockFetchOnce(status: number, body: unknown) {
  (global.fetch as jest.Mock).mockResolvedValueOnce({
    ok: status >= 200 && status < 300,
    status,
    text: async () => JSON.stringify(body),
    json: async () => body,
  });
}

const tokenResponse = (overrides: Partial<Record<string, unknown>> = {}) => ({
  access_token: 'access-token-1',
  refresh_token: 'refresh-token-1',
  token_type: 'Bearer',
  expires_in: 3600,
  person_id: 'person-1',
  user_account_id: 'account-1',
  is_new_user: false,
  ...overrides,
});

describe('AuthContext', () => {
  beforeEach(() => {
    global.fetch = jest.fn();
    mockSecureStore.clear();
  });

  it('starts in the unauthenticated state when no tokens are stored', async () => {
    const { result } = await renderHook(() => useAuth(), { wrapper });

    await waitFor(() => expect(result.current.status).toBe('unauthenticated'));
    expect(global.fetch).not.toHaveBeenCalled();
  });

  it('moves to authenticated after a successful, already-verified Email+Password login with a name already set', async () => {
    mockFetchOnce(200, tokenResponse()); // POST /auth/email/login
    mockFetchOnce(200, { id: 'person-1', word_press_user_id: 1, email_verified: true, family_name: '舞台', given_name: '芸術' }); // GET /me probe

    const { result } = await renderHook(() => useAuth(), { wrapper });
    await waitFor(() => expect(result.current.status).toBe('unauthenticated'));

    let loginResult: Awaited<ReturnType<typeof result.current.loginWithEmail>> | undefined;
    await act(async () => {
      loginResult = await result.current.loginWithEmail('person@example.com', 'password123');
    });

    expect(loginResult).toEqual({
      ok: true,
      emailVerified: true,
      hasName: true,
      familyNameHint: null,
      givenNameHint: null,
    });
    expect(result.current.status).toBe('authenticated');

    const [url, init] = (global.fetch as jest.Mock).mock.calls[0];
    expect(url).toContain('/auth/email/login');
    expect(JSON.parse(init.body)).toEqual({ email: 'person@example.com', password: 'password123' });
  });

  /**
   * StageArt Authentication Phase 6: a returning, already-verified Email
   * user who has never completed set-name.tsx yet - `hasName: false`
   * must still surface even though the account is fully verified and a
   * real session is established (the two gates are independent).
   */
  it('reports hasName: false for a verified Email+Password login with no name set yet', async () => {
    mockFetchOnce(200, tokenResponse());
    mockFetchOnce(200, { id: 'person-1', word_press_user_id: 1, email_verified: true, family_name: null, given_name: null });

    const { result } = await renderHook(() => useAuth(), { wrapper });
    await waitFor(() => expect(result.current.status).toBe('unauthenticated'));

    let loginResult: Awaited<ReturnType<typeof result.current.loginWithEmail>> | undefined;
    await act(async () => {
      loginResult = await result.current.loginWithEmail('person@example.com', 'password123');
    });

    expect(loginResult).toEqual({
      ok: true,
      emailVerified: true,
      hasName: false,
      familyNameHint: null,
      givenNameHint: null,
    });
    expect(result.current.status).toBe('authenticated');
  });

  /**
   * A real-device correction: this is the exact scenario that regressed
   * in practice - an unverified account that logs in successfully must
   * NOT be treated as a normal StageArt session (no persisted tokens,
   * status stays 'unauthenticated'), or a subsequent app relaunch
   * restores a session that then had to be redirected away from Home
   * on every single boot instead of simply showing the login screen.
   */
  it('does not establish a session for a successful but still-unverified Email+Password login', async () => {
    mockFetchOnce(200, tokenResponse()); // POST /auth/email/login
    mockFetchOnce(200, { id: 'person-1', word_press_user_id: 1, email_verified: false }); // GET /me probe

    const { result } = await renderHook(() => useAuth(), { wrapper });
    await waitFor(() => expect(result.current.status).toBe('unauthenticated'));

    let loginResult: Awaited<ReturnType<typeof result.current.loginWithEmail>> | undefined;
    await act(async () => {
      loginResult = await result.current.loginWithEmail('person@example.com', 'password123');
    });

    expect(loginResult).toEqual({
      ok: true,
      emailVerified: false,
      hasName: false,
      familyNameHint: null,
      givenNameHint: null,
    });
    expect(result.current.status).toBe('unauthenticated');
    expect(mockSecureStore.has('stageart_access_token')).toBe(false);
    expect(mockSecureStore.has('stageart_refresh_token')).toBe(false);
  });

  /**
   * A real-device correction: a freshly-registered account is always
   * unverified by construction (EmailCredential.emailVerifiedAt starts
   * null), so registerWithEmail() never establishes a session at all -
   * unlike the pre-correction design, where the Backend's own
   * Access/Refresh Token pair was persisted immediately, making the
   * user "logged in" before ever confirming their email.
   */
  it('does not establish a session after a successful Email+Password registration', async () => {
    mockFetchOnce(200, tokenResponse({ is_new_user: true })); // POST /auth/email/register only

    const { result } = await renderHook(() => useAuth(), { wrapper });
    await waitFor(() => expect(result.current.status).toBe('unauthenticated'));

    let registerResult: Awaited<ReturnType<typeof result.current.registerWithEmail>> | undefined;
    await act(async () => {
      registerResult = await result.current.registerWithEmail('new@example.com', 'password123');
    });

    expect(registerResult).toEqual({
      ok: true,
      emailVerified: false,
      hasName: false,
      familyNameHint: null,
      givenNameHint: null,
    });
    expect(result.current.status).toBe('unauthenticated');
    expect(mockSecureStore.has('stageart_access_token')).toBe(false);
    expect(mockSecureStore.has('stageart_refresh_token')).toBe(false);
    expect(global.fetch).toHaveBeenCalledTimes(1);
  });

  /**
   * StageArt Authentication Phase 6: Google logins are always
   * email-verified (no probe needed for that), but still go through the
   * identical GET /me hasName gate as Email accounts - no "Google skips
   * this" exception. When the Google ID Token carried family_name/
   * given_name claims, they surface as UI-only hints on the result even
   * though hasName is false (set-name.tsx pre-fills from them).
   */
  it('moves to authenticated after a successful Google login and still probes GET /me for hasName', async () => {
    mockFetchOnce(200, tokenResponse({ is_new_user: true, family_name_hint: '舞台', given_name_hint: '芸術' }));
    mockFetchOnce(200, { id: 'person-1', word_press_user_id: 1, email_verified: true, family_name: null, given_name: null }); // GET /me probe

    const { result } = await renderHook(() => useAuth(), { wrapper });
    await waitFor(() => expect(result.current.status).toBe('unauthenticated'));

    let googleResult: Awaited<ReturnType<typeof result.current.loginWithGoogle>> | undefined;
    await act(async () => {
      googleResult = await result.current.loginWithGoogle('google-id-token-abc');
    });

    expect(googleResult).toEqual({
      ok: true,
      emailVerified: true,
      hasName: false,
      familyNameHint: '舞台',
      givenNameHint: '芸術',
    });
    expect(result.current.status).toBe('authenticated');
    expect(global.fetch).toHaveBeenCalledTimes(2);

    const [url, init] = (global.fetch as jest.Mock).mock.calls[0];
    expect(url).toContain('/auth/google');
    expect(JSON.parse(init.body)).toEqual({ id_token: 'google-id-token-abc' });
  });

  it('stays unauthenticated and surfaces a StageArt-native message on a wrong-password 401 (no WordPress terms)', async () => {
    mockFetchOnce(401, { code: 'stageart_invalid_credentials', message: 'The email address or password is incorrect.' });

    const { result } = await renderHook(() => useAuth(), { wrapper });
    await waitFor(() => expect(result.current.status).toBe('unauthenticated'));

    let loginResult: Awaited<ReturnType<typeof result.current.loginWithEmail>> | undefined;
    await act(async () => {
      loginResult = await result.current.loginWithEmail('person@example.com', 'wrong-password');
    });

    expect(loginResult).toEqual({ ok: false, message: 'メールアドレスまたはパスワードが正しくありません。' });
    expect(result.current.status).toBe('unauthenticated');
  });

  it('returns to unauthenticated after logout, and clears stored tokens', async () => {
    mockFetchOnce(200, tokenResponse()); // POST /auth/email/login
    mockFetchOnce(200, { id: 'person-1', word_press_user_id: 1, email_verified: true }); // GET /me probe

    const { result } = await renderHook(() => useAuth(), { wrapper });
    await waitFor(() => expect(result.current.status).toBe('unauthenticated'));

    await act(async () => {
      await result.current.loginWithEmail('person@example.com', 'password123');
    });
    expect(result.current.status).toBe('authenticated');

    mockFetchOnce(200, { success: true }); // POST /auth/logout

    await act(async () => {
      await result.current.logout();
    });

    expect(result.current.status).toBe('unauthenticated');
    expect(mockSecureStore.has('stageart_access_token')).toBe(false);
    expect(mockSecureStore.has('stageart_refresh_token')).toBe(false);
  });

  it('restores the session at boot via POST /auth/refresh when a Refresh Token is stored', async () => {
    mockSecureStore.set('stageart_access_token', 'stale-access-token');
    mockSecureStore.set('stageart_refresh_token', 'stored-refresh-token');
    mockFetchOnce(200, { access_token: 'fresh-access-token', token_type: 'Bearer', expires_in: 3600 });

    const { result } = await renderHook(() => useAuth(), { wrapper });

    await waitFor(() => expect(result.current.status).toBe('authenticated'));

    const [url, init] = (global.fetch as jest.Mock).mock.calls[0];
    expect(url).toContain('/auth/refresh');
    expect(JSON.parse(init.body)).toEqual({ refresh_token: 'stored-refresh-token' });
  });

  it('falls back to unauthenticated and clears storage when the stored Refresh Token is invalid', async () => {
    mockSecureStore.set('stageart_access_token', 'stale-access-token');
    mockSecureStore.set('stageart_refresh_token', 'expired-refresh-token');
    mockFetchOnce(401, { code: 'stageart_invalid_refresh_token', message: 'invalid' });

    const { result } = await renderHook(() => useAuth(), { wrapper });

    await waitFor(() => expect(result.current.status).toBe('unauthenticated'));
    expect(mockSecureStore.has('stageart_access_token')).toBe(false);
    expect(mockSecureStore.has('stageart_refresh_token')).toBe(false);
  });
});
