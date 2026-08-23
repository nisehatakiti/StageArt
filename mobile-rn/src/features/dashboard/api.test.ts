import { ApiClient } from '@/api/client';

import { fetchMyDashboard } from './api';

describe('Dashboard api', () => {
  it('calls GET /me/dashboard with no parameters', async () => {
    global.fetch = jest.fn().mockResolvedValue({
      ok: true,
      status: 200,
      text: async () => JSON.stringify({ upcoming_rehearsals: [], notifications: [] }),
    });
    const client = new ApiClient(() => 'access-token', 'https://example.test/wp-json/stageart/v1');

    const result = await fetchMyDashboard(client);

    const [url, options] = (global.fetch as jest.Mock).mock.calls[0];
    expect(url).toBe('https://example.test/wp-json/stageart/v1/me/dashboard');
    expect(options.method).toBe('GET');
    expect(result).toEqual({ upcoming_rehearsals: [], notifications: [] });
  });
});
