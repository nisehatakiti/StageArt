import { ApiClient } from '@/api/client';

import { fetchProductionNotifications, markNotificationRead } from './api';

describe('Notifications api', () => {
  beforeEach(() => {
    global.fetch = jest.fn().mockResolvedValue({
      ok: true,
      status: 200,
      text: async () => '[]',
    });
  });

  it('fetches Notifications for a Production', async () => {
    const client = new ApiClient(() => 'access-token', 'https://example.test/wp-json/stageart/v1');

    await fetchProductionNotifications(client, 'prod-1');

    const url = (global.fetch as jest.Mock).mock.calls[0][0] as string;
    expect(url).toBe('https://example.test/wp-json/stageart/v1/productions/prod-1/notifications');
  });

  it('sends PATCH to /notifications/{id}/read with no body (Phase 7.1)', async () => {
    (global.fetch as jest.Mock).mockResolvedValue({
      ok: true,
      status: 200,
      text: async () => JSON.stringify({ id: 'notif-1', is_read: true }),
    });
    const client = new ApiClient(() => 'access-token', 'https://example.test/wp-json/stageart/v1');

    const result = await markNotificationRead(client, 'notif-1');

    const [url, options] = (global.fetch as jest.Mock).mock.calls[0];
    expect(url).toBe('https://example.test/wp-json/stageart/v1/notifications/notif-1/read');
    expect(options.method).toBe('PATCH');
    expect(result).toEqual({ id: 'notif-1', is_read: true });
  });
});
