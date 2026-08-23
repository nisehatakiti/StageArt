import { ApiClient } from '@/api/client';
import { NetworkError } from '@/api/errors';

import {
  addEmailCredential,
  changePassword,
  linkGoogleIdentity,
  loginWithEmail,
  loginWithGoogle,
  logout,
  refreshAccessToken,
  registerWithEmail,
  requestEmailVerification,
  requestPasswordReset,
  resetPassword,
  verifyEmail,
} from './api';

const BASE_URL = 'https://dev-stageart.hatakiti.com/stageart-test/wp-json/stageart/v1';

function mockFetchOnce(status: number, body: unknown) {
  (global.fetch as jest.Mock).mockResolvedValueOnce({
    ok: status >= 200 && status < 300,
    status,
    text: async () => JSON.stringify(body),
    json: async () => body,
  });
}

/**
 * Direct function-level tests for every auth endpoint this Client calls
 * (Backend Phase 2 report's API surface). Several of these correspond to
 * screens (register/login/reset/verify/link) whose full type-then-submit
 * interaction hits the renderRouter()-specific local-state-press
 * limitation already documented in schedule-comment-post.test.tsx (no
 * navigation follows the local state update, so the DOM update is not
 * observed) - testing the underlying function directly, exactly as that
 * existing test does, verifies the real request/response behavior
 * without depending on that unreliable interaction path.
 */
describe('auth api', () => {
  beforeEach(() => {
    global.fetch = jest.fn();
  });

  it('registerWithEmail posts to /auth/email/register with email+password', async () => {
    mockFetchOnce(201, {
      access_token: 'a1',
      refresh_token: 'r1',
      token_type: 'Bearer',
      expires_in: 3600,
      person_id: 'person-1',
      user_account_id: 'account-1',
      is_new_user: true,
    });

    const result = await registerWithEmail('new@example.com', 'password123');

    const [url, init] = (global.fetch as jest.Mock).mock.calls[0];
    expect(url).toBe(`${BASE_URL}/auth/email/register`);
    expect(JSON.parse(init.body)).toEqual({ email: 'new@example.com', password: 'password123' });
    expect(result.is_new_user).toBe(true);
  });

  it('registerWithEmail surfaces a 409 (duplicate email) as ApiError', async () => {
    mockFetchOnce(409, { code: 'stageart_email_already_in_use', message: 'already registered' });

    await expect(registerWithEmail('dup@example.com', 'password123')).rejects.toMatchObject({ statusCode: 409 });
  });

  it('loginWithEmail posts to /auth/email/login', async () => {
    mockFetchOnce(200, {
      access_token: 'a1',
      refresh_token: 'r1',
      token_type: 'Bearer',
      expires_in: 3600,
      person_id: 'person-1',
      user_account_id: 'account-1',
      is_new_user: false,
    });

    await loginWithEmail('person@example.com', 'password123');

    const [url, init] = (global.fetch as jest.Mock).mock.calls[0];
    expect(url).toBe(`${BASE_URL}/auth/email/login`);
    expect(JSON.parse(init.body)).toEqual({ email: 'person@example.com', password: 'password123' });
  });

  it('loginWithEmail with wrong credentials surfaces a 401 ApiError', async () => {
    mockFetchOnce(401, { code: 'stageart_invalid_credentials', message: 'wrong' });

    await expect(loginWithEmail('person@example.com', 'wrong')).rejects.toMatchObject({ statusCode: 401 });
  });

  it('loginWithGoogle posts the Google ID Token to /auth/google as id_token, never as a Bearer header', async () => {
    mockFetchOnce(200, {
      access_token: 'a1',
      refresh_token: 'r1',
      token_type: 'Bearer',
      expires_in: 3600,
      person_id: 'person-1',
      user_account_id: 'account-1',
      is_new_user: true,
    });

    await loginWithGoogle('google-id-token-xyz');

    const [url, init] = (global.fetch as jest.Mock).mock.calls[0];
    expect(url).toBe(`${BASE_URL}/auth/google`);
    expect(JSON.parse(init.body)).toEqual({ id_token: 'google-id-token-xyz' });
    expect(init.headers.Authorization).toBeUndefined();
  });

  it('refreshAccessToken posts the Refresh Token to /auth/refresh', async () => {
    mockFetchOnce(200, { access_token: 'fresh', token_type: 'Bearer', expires_in: 3600 });

    const result = await refreshAccessToken('stored-refresh-token');

    const [url, init] = (global.fetch as jest.Mock).mock.calls[0];
    expect(url).toBe(`${BASE_URL}/auth/refresh`);
    expect(JSON.parse(init.body)).toEqual({ refresh_token: 'stored-refresh-token' });
    expect(result.access_token).toBe('fresh');
  });

  it('logout posts the Refresh Token to /auth/logout', async () => {
    mockFetchOnce(200, { success: true });

    await logout('stored-refresh-token');

    const [url, init] = (global.fetch as jest.Mock).mock.calls[0];
    expect(url).toBe(`${BASE_URL}/auth/logout`);
    expect(JSON.parse(init.body)).toEqual({ refresh_token: 'stored-refresh-token' });
  });

  it('requestPasswordReset always resolves the same way regardless of account existence (Backend anti-enumeration)', async () => {
    mockFetchOnce(200, { success: true });

    const result = await requestPasswordReset('maybe-registered@example.com');

    expect(result).toEqual({ success: true });
    const [url, init] = (global.fetch as jest.Mock).mock.calls[0];
    expect(url).toBe(`${BASE_URL}/auth/password/reset-request`);
    expect(JSON.parse(init.body)).toEqual({ email: 'maybe-registered@example.com' });
  });

  it('resetPassword posts token + new_password to /auth/password/reset', async () => {
    mockFetchOnce(200, { success: true });

    await resetPassword('reset-token-abc', 'newpassword2');

    const [url, init] = (global.fetch as jest.Mock).mock.calls[0];
    expect(url).toBe(`${BASE_URL}/auth/password/reset`);
    expect(JSON.parse(init.body)).toEqual({ token: 'reset-token-abc', new_password: 'newpassword2' });
  });

  it('resetPassword with an invalid/expired token surfaces a 401 ApiError', async () => {
    mockFetchOnce(401, { code: 'stageart_invalid_password_reset_token', message: 'invalid' });

    await expect(resetPassword('bad-token', 'newpassword2')).rejects.toMatchObject({ statusCode: 401 });
  });

  it('verifyEmail posts the token to /auth/email/verify, unauthenticated', async () => {
    mockFetchOnce(200, { success: true });

    await verifyEmail('verify-token-abc');

    const [url, init] = (global.fetch as jest.Mock).mock.calls[0];
    expect(url).toBe(`${BASE_URL}/auth/email/verify`);
    expect(JSON.parse(init.body)).toEqual({ token: 'verify-token-abc' });
    expect(init.headers.Authorization).toBeUndefined();
  });

  it('wraps a connectivity failure in NetworkError for the public endpoints', async () => {
    (global.fetch as jest.Mock).mockRejectedValueOnce(new TypeError('Failed to fetch'));

    await expect(loginWithEmail('a@example.com', 'password123')).rejects.toBeInstanceOf(NetworkError);
  });

  describe('authenticated self-service (require a StageArt session)', () => {
    function client() {
      return new ApiClient(() => 'access-token', BASE_URL);
    }

    it('changePassword posts current_password/new_password with a Bearer Authorization header', async () => {
      mockFetchOnce(200, { success: true });

      await changePassword(client(), 'oldpass1', 'newpass2');

      const [url, init] = (global.fetch as jest.Mock).mock.calls[0];
      expect(url).toBe(`${BASE_URL}/user-accounts/email-credential/password`);
      expect(init.headers.Authorization).toBe('Bearer access-token');
      expect(JSON.parse(init.body)).toEqual({ current_password: 'oldpass1', new_password: 'newpass2' });
    });

    it('changePassword with the wrong current password surfaces a 401 ApiError', async () => {
      mockFetchOnce(401, { code: 'stageart_current_password_incorrect', message: 'wrong' });

      await expect(changePassword(client(), 'wrong', 'newpass2')).rejects.toMatchObject({ statusCode: 401 });
    });

    it('requestEmailVerification posts to /user-accounts/email-credential/verify-request', async () => {
      mockFetchOnce(200, { success: true });

      await requestEmailVerification(client());

      const [url, init] = (global.fetch as jest.Mock).mock.calls[0];
      expect(url).toBe(`${BASE_URL}/user-accounts/email-credential/verify-request`);
      expect(init.headers.Authorization).toBe('Bearer access-token');
    });

    it('addEmailCredential (Google -> Email linking) posts to the existing /user-accounts/email-credential endpoint', async () => {
      mockFetchOnce(201, { id: 'account-1', person_id: 'person-1', status: 'ACTIVE', created_at: '', updated_at: '' });

      await addEmailCredential(client(), 'linked@example.com', 'password123');

      const [url, init] = (global.fetch as jest.Mock).mock.calls[0];
      expect(url).toBe(`${BASE_URL}/user-accounts/email-credential`);
      expect(JSON.parse(init.body)).toEqual({ email: 'linked@example.com', password: 'password123' });
    });

    it('linkGoogleIdentity (Email -> Google linking) posts id_token to the existing /user-accounts/external-identity endpoint', async () => {
      mockFetchOnce(200, { id: 'account-1', person_id: 'person-1', status: 'ACTIVE', created_at: '', updated_at: '' });

      await linkGoogleIdentity(client(), 'google-id-token-xyz');

      const [url, init] = (global.fetch as jest.Mock).mock.calls[0];
      expect(url).toBe(`${BASE_URL}/user-accounts/external-identity`);
      expect(JSON.parse(init.body)).toEqual({ id_token: 'google-id-token-xyz' });
    });

    it('linkGoogleIdentity already linked to a different account surfaces a 409 ApiError (no auto-merge)', async () => {
      mockFetchOnce(409, { code: 'stageart_external_identity_already_linked', message: 'already linked elsewhere' });

      await expect(linkGoogleIdentity(client(), 'google-id-token-xyz')).rejects.toMatchObject({ statusCode: 409 });
    });
  });
});
