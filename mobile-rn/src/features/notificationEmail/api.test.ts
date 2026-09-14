import { ApiClient } from '@/api/client';

import { fetchNotificationEmailSettings, requestNotificationEmailChange, verifyNotificationEmailChange } from './api';

const BASE_URL = 'https://dev-api.stageart.top/wp-json/stageart/v1';

function mockFetchOnce(status: number, body: unknown) {
  (global.fetch as jest.Mock).mockResolvedValueOnce({
    ok: status >= 200 && status < 300,
    status,
    text: async () => JSON.stringify(body),
    json: async () => body,
  });
}

function client() {
  return new ApiClient(() => 'access-token', BASE_URL);
}

/**
 * 通知用Email確認・変更機能: direct function-level tests, exactly like
 * features/auth/api.test.ts's own "authenticated self-service" block -
 * the toggle-then-submit interaction on /account itself hits this
 * codebase's established renderRouter()-local-state-press limitation
 * (see mypage-account-linking-render.test.tsx's own docblock), so the
 * real request/response behavior is verified here instead.
 */
describe('notificationEmail api', () => {
  beforeEach(() => {
    global.fetch = jest.fn();
  });

  it('fetchNotificationEmailSettings gets /me/notification-email with a Bearer Authorization header', async () => {
    const body = { current_email: 'notify@example.com', source: 'NOTIFICATION_EMAIL', pending_email: null };
    mockFetchOnce(200, body);

    const result = await fetchNotificationEmailSettings(client());

    const [url, init] = (global.fetch as jest.Mock).mock.calls[0];
    expect(url).toBe(`${BASE_URL}/me/notification-email`);
    expect(init.headers.Authorization).toBe('Bearer access-token');
    expect(result).toEqual(body);
  });

  it('requestNotificationEmailChange posts { email } to /me/notification-email/change-request', async () => {
    mockFetchOnce(200, { status: 'PENDING_VERIFICATION' });

    const result = await requestNotificationEmailChange(client(), 'new@example.com');

    const [url, init] = (global.fetch as jest.Mock).mock.calls[0];
    expect(url).toBe(`${BASE_URL}/me/notification-email/change-request`);
    expect(init.headers.Authorization).toBe('Bearer access-token');
    expect(JSON.parse(init.body)).toEqual({ email: 'new@example.com' });
    expect(result).toEqual({ status: 'PENDING_VERIFICATION' });
  });

  it('requestNotificationEmailChange surfaces a 422 ApiError for a malformed email', async () => {
    mockFetchOnce(422, { code: 'stageart_notification_email_invalid', message: 'invalid' });

    await expect(requestNotificationEmailChange(client(), 'not-an-email')).rejects.toMatchObject({ statusCode: 422 });
  });

  it('verifyNotificationEmailChange posts { token } to the public /notification-email/verify endpoint (no Authorization header)', async () => {
    mockFetchOnce(200, { success: true });

    await verifyNotificationEmailChange('a-token');

    const [url, init] = (global.fetch as jest.Mock).mock.calls[0];
    expect(url).toBe(`${BASE_URL}/notification-email/verify`);
    expect(init.headers.Authorization).toBeUndefined();
    expect(JSON.parse(init.body)).toEqual({ token: 'a-token' });
  });

  it('verifyNotificationEmailChange surfaces a 401 ApiError for an invalid/expired token', async () => {
    mockFetchOnce(401, { code: 'stageart_invalid_notification_email_change_token', message: 'invalid' });

    await expect(verifyNotificationEmailChange('bad-token')).rejects.toMatchObject({ statusCode: 401 });
  });
});
