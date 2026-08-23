import { ApiClient } from '@/api/client';

import {
  deleteScheduleComment,
  fetchProductionTimetableItems,
  fetchRehearsalComments,
  fetchTimetableItemComments,
  postRehearsalComment,
  postTimetableItemComment,
  updateScheduleComment,
} from './api';

describe('Schedule api (§40: from/to query params)', () => {
  beforeEach(() => {
    global.fetch = jest.fn().mockResolvedValue({
      ok: true,
      status: 200,
      text: async () => '[]',
    });
  });

  it('omits from/to entirely for Full Range (no query string)', async () => {
    const client = new ApiClient(() => 'access-token', 'https://example.test/wp-json/stageart/v1');

    await fetchProductionTimetableItems(client, 'prod-1');

    const url = (global.fetch as jest.Mock).mock.calls[0][0] as string;
    expect(url).toBe('https://example.test/wp-json/stageart/v1/productions/prod-1/timetable-items');
  });

  it('includes from/to as plain offset-free wall-clock query params for the default range', async () => {
    const client = new ApiClient(() => 'access-token', 'https://example.test/wp-json/stageart/v1');

    await fetchProductionTimetableItems(client, 'prod-1', { from: '2026-08-18 00:00:00', to: '2026-08-20 00:00:00' });

    const url = (global.fetch as jest.Mock).mock.calls[0][0] as string;
    expect(url).toContain('from=2026-08-18+00%3A00%3A00');
    expect(url).toContain('to=2026-08-20+00%3A00%3A00');
  });

  it('fetches Schedule Comments for a Timetable Item', async () => {
    const client = new ApiClient(() => 'access-token', 'https://example.test/wp-json/stageart/v1');

    await fetchTimetableItemComments(client, 'item-1');

    const url = (global.fetch as jest.Mock).mock.calls[0][0] as string;
    expect(url).toBe('https://example.test/wp-json/stageart/v1/timetable-items/item-1/schedule-comments');
  });

  it('posts a Schedule Comment body to the correct route', async () => {
    (global.fetch as jest.Mock).mockResolvedValue({
      ok: true,
      status: 201,
      text: async () => JSON.stringify({ id: 'c1' }),
    });
    const client = new ApiClient(() => 'access-token', 'https://example.test/wp-json/stageart/v1');

    await postTimetableItemComment(client, 'item-1', 'コメント本文');

    const [url, options] = (global.fetch as jest.Mock).mock.calls[0];
    expect(url).toBe('https://example.test/wp-json/stageart/v1/timetable-items/item-1/schedule-comments');
    expect(options.method).toBe('POST');
    expect(JSON.parse(options.body)).toEqual({ body: 'コメント本文' });
  });

  it('fetches Schedule Comments for a Rehearsal (Phase 6.3)', async () => {
    const client = new ApiClient(() => 'access-token', 'https://example.test/wp-json/stageart/v1');

    await fetchRehearsalComments(client, 'rehearsal-1');

    const url = (global.fetch as jest.Mock).mock.calls[0][0] as string;
    expect(url).toBe('https://example.test/wp-json/stageart/v1/rehearsals/rehearsal-1/schedule-comments');
  });

  it('posts a Rehearsal Comment body to the correct route', async () => {
    (global.fetch as jest.Mock).mockResolvedValue({
      ok: true,
      status: 201,
      text: async () => JSON.stringify({ id: 'c1' }),
    });
    const client = new ApiClient(() => 'access-token', 'https://example.test/wp-json/stageart/v1');

    await postRehearsalComment(client, 'rehearsal-1', 'コメント本文');

    const [url, options] = (global.fetch as jest.Mock).mock.calls[0];
    expect(url).toBe('https://example.test/wp-json/stageart/v1/rehearsals/rehearsal-1/schedule-comments');
    expect(options.method).toBe('POST');
    expect(JSON.parse(options.body)).toEqual({ body: 'コメント本文' });
  });

  it('updates a Schedule Comment body via PUT to the shared /schedule-comments/{id} route (Phase 6.4)', async () => {
    (global.fetch as jest.Mock).mockResolvedValue({
      ok: true,
      status: 200,
      text: async () => JSON.stringify({ id: 'c1' }),
    });
    const client = new ApiClient(() => 'access-token', 'https://example.test/wp-json/stageart/v1');

    await updateScheduleComment(client, 'c1', '編集後の本文');

    const [url, options] = (global.fetch as jest.Mock).mock.calls[0];
    expect(url).toBe('https://example.test/wp-json/stageart/v1/schedule-comments/c1');
    expect(options.method).toBe('PUT');
    expect(JSON.parse(options.body)).toEqual({ body: '編集後の本文' });
  });

  it('deletes a Schedule Comment via DELETE to the shared /schedule-comments/{id} route', async () => {
    (global.fetch as jest.Mock).mockResolvedValue({
      ok: true,
      status: 204,
      text: async () => '',
    });
    const client = new ApiClient(() => 'access-token', 'https://example.test/wp-json/stageart/v1');

    await deleteScheduleComment(client, 'c1');

    const [url, options] = (global.fetch as jest.Mock).mock.calls[0];
    expect(url).toBe('https://example.test/wp-json/stageart/v1/schedule-comments/c1');
    expect(options.method).toBe('DELETE');
  });
});
