import type { ApiClient } from '@/api/client';

import { fetchTimetablePrintPdf } from './api';

describe('fetchTimetablePrintPdf', () => {
  it('requests the print endpoint with paperSize/orientation/format=pdf and returns raw bytes', async () => {
    const bytes = new Uint8Array([0x25, 0x50, 0x44, 0x46]).buffer; // "%PDF"
    const getBytes = jest.fn().mockResolvedValue(bytes);
    const client = { getBytes } as unknown as ApiClient;

    const result = await fetchTimetablePrintPdf(client, 'prod-1', { paperSize: 'A3', orientation: 'landscape' });

    expect(getBytes).toHaveBeenCalledWith('/productions/prod-1/timetable/print', {
      paperSize: 'A3',
      orientation: 'landscape',
      format: 'pdf',
    });
    expect(result).toBe(bytes);
  });

  it('propagates an ApiError from getBytes without swallowing it', async () => {
    const client = { getBytes: jest.fn().mockRejectedValue(new Error('403')) } as unknown as ApiClient;

    await expect(fetchTimetablePrintPdf(client, 'prod-1', { paperSize: 'A4', orientation: 'portrait' })).rejects.toThrow('403');
  });
});
