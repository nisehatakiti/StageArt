import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { productionOne } from '../__fixtures__/productionShellFixtures';

const mockWrite = jest.fn();
const mockShareAsync = jest.fn();
const mockIsAvailableAsync = jest.fn();

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

jest.mock('expo-file-system', () => ({
  File: jest.fn().mockImplementation(() => ({ write: mockWrite, uri: 'file:///cache/prod-1-timetable-print.pdf' })),
  Paths: { cache: 'file:///cache' },
}));

jest.mock('expo-print', () => ({ printAsync: jest.fn().mockResolvedValue(undefined) }));

jest.mock('expo-sharing', () => ({
  isAvailableAsync: (...args: unknown[]) => mockIsAvailableAsync(...args),
  shareAsync: (...args: unknown[]) => mockShareAsync(...args),
}));

/**
 * Verifies the screen's default selection and the Share wiring
 * end-to-end. Switching the selected paper option via `fireEvent.press`
 * before asserting is deliberately not exercised here: it is the same
 * class of limitation already documented in schedule-range-toggle.test.tsx
 * (a local useState-only press inside a renderRouter()-mounted screen,
 * with no navigation to anchor on, does not reliably re-render within
 * this harness) - not an application defect. That every PAPER_OPTIONS
 * choice is actually threaded through to the request/mutation correctly
 * is instead proven directly in usePrintTimetable.test.tsx (which calls
 * the hook with an explicit A3/landscape selection and asserts the
 * resulting file write + Print/Share call), independent of this
 * press-driven harness limitation.
 */
describe('Print View screen: default selection and Share', () => {
  it('renders A4 縦 selected by default and shares the fetched PDF on Share', async () => {
    let lastPrintUrl: string | null = null;

    global.fetch = jest.fn(async (input: unknown) => {
      const url = String(input);

      if (url.endsWith('/auth/refresh')) {
        return {
          ok: true,
          status: 200,
          text: async () => JSON.stringify({ access_token: 'refreshed-token', token_type: 'Bearer', expires_in: 3600 }),
        } as Response;
      }

      if (url.endsWith('/productions/prod-1')) {
        return { ok: true, status: 200, text: async () => JSON.stringify(productionOne), json: async () => productionOne } as Response;
      }

      if (url.includes('/timetable/print')) {
        lastPrintUrl = url;
        const bytes = new Uint8Array([0x25, 0x50, 0x44, 0x46]);
        return { ok: true, status: 200, arrayBuffer: async () => bytes.buffer } as unknown as Response;
      }

      throw new Error(`Unmocked fetch: ${url}`);
    });
    mockIsAvailableAsync.mockResolvedValue(true);
    mockShareAsync.mockResolvedValue(undefined);

    renderRouter('src/app', { initialUrl: '/production/prod-1/schedule/print' });

    await waitFor(() => expect(screen.getByText('● A4 縦')).toBeVisible());

    fireEvent.press(screen.getByTestId('print-share-button'));

    await waitFor(() => expect(mockShareAsync).toHaveBeenCalledWith('file:///cache/prod-1-timetable-print.pdf', expect.anything()));

    expect(lastPrintUrl).toContain('paperSize=A4');
    expect(lastPrintUrl).toContain('orientation=portrait');
    expect(mockWrite).toHaveBeenCalled();
  });
});
