import type { ApiClient } from '@/api/client';

export type PaperSize = 'A4' | 'A3';
export type Orientation = 'portrait' | 'landscape';

/**
 * GET /productions/{id}/timetable/print (Backend Phase 4.5's
 * PrintViewRestController). `format=pdf` is always passed explicitly -
 * the endpoint defaults to pdf already, but this Client never relies on
 * a Backend default silently changing under it. Returns raw PDF bytes
 * (client.getBytes bypasses JSON decoding on success).
 */
export function fetchTimetablePrintPdf(
  client: ApiClient,
  productionId: string,
  options: { paperSize: PaperSize; orientation: Orientation }
): Promise<ArrayBuffer> {
  return client.getBytes(`/productions/${productionId}/timetable/print`, {
    paperSize: options.paperSize,
    orientation: options.orientation,
    format: 'pdf',
  });
}
