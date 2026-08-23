import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, renderHook, waitFor } from '@testing-library/react-native';
import type { PropsWithChildren } from 'react';

import { AuthProvider, useAuth } from '@/auth/AuthContext';

import { usePrintTimetable } from './usePrintTimetable';

const mockSecureStore = new Map<string, string>();
const mockWrite = jest.fn();
const mockPrintAsync = jest.fn();
const mockIsAvailableAsync = jest.fn();
const mockShareAsync = jest.fn();

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) => mockSecureStore.get(key) ?? null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

jest.mock('expo-file-system', () => ({
  File: jest.fn().mockImplementation(() => ({ write: mockWrite, uri: 'file:///cache/prod-1-timetable-print.pdf' })),
  Paths: { cache: 'file:///cache' },
}));

jest.mock('expo-print', () => ({ printAsync: (...args: unknown[]) => mockPrintAsync(...args) }));

jest.mock('expo-sharing', () => ({
  isAvailableAsync: (...args: unknown[]) => mockIsAvailableAsync(...args),
  shareAsync: (...args: unknown[]) => mockShareAsync(...args),
}));

function wrapper({ children }: PropsWithChildren) {
  const queryClient = new QueryClient();
  return (
    <QueryClientProvider client={queryClient}>
      <AuthProvider>{children}</AuthProvider>
    </QueryClientProvider>
  );
}

describe('usePrintTimetable', () => {
  beforeEach(() => {
    mockSecureStore.clear();
    mockSecureStore.set('stageart_access_token', 'mock-access-token');
    mockSecureStore.set('stageart_refresh_token', 'mock-refresh-token');
    mockWrite.mockClear();
    mockPrintAsync.mockClear().mockResolvedValue(undefined);
    mockIsAvailableAsync.mockClear().mockResolvedValue(true);
    mockShareAsync.mockClear().mockResolvedValue(undefined);

    global.fetch = jest.fn(async (input: unknown) => {
      const url = String(input);
      if (url.endsWith('/auth/refresh')) {
        return {
          ok: true,
          status: 200,
          text: async () => JSON.stringify({ access_token: 'refreshed-token', token_type: 'Bearer', expires_in: 3600 }),
        } as Response;
      }
      if (url.includes('/timetable/print')) {
        const bytes = new Uint8Array([0x25, 0x50, 0x44, 0x46]); // "%PDF"
        return { ok: true, status: 200, arrayBuffer: async () => bytes.buffer } as unknown as Response;
      }
      throw new Error(`Unmocked fetch: ${url}`);
    });
  });

  async function renderReady() {
    const { result } = await renderHook(() => ({ auth: useAuth(), print: usePrintTimetable('prod-1') }), { wrapper });
    await waitFor(() => expect(result.current.auth.status).toBe('authenticated'));
    return result;
  }

  it('writes the fetched PDF bytes to a cache file and calls expo-print for a print action', async () => {
    const result = await renderReady();

    await act(async () => {
      await result.current.print.mutateAsync({ paperSize: 'A4', orientation: 'portrait', action: 'print' });
    });

    expect(mockWrite).toHaveBeenCalledWith(new Uint8Array([0x25, 0x50, 0x44, 0x46]));
    expect(mockPrintAsync).toHaveBeenCalledWith({ uri: 'file:///cache/prod-1-timetable-print.pdf' });
    expect(mockShareAsync).not.toHaveBeenCalled();
  });

  it('calls expo-sharing for a share action', async () => {
    const result = await renderReady();

    await act(async () => {
      await result.current.print.mutateAsync({ paperSize: 'A3', orientation: 'landscape', action: 'share' });
    });

    expect(mockIsAvailableAsync).toHaveBeenCalled();
    expect(mockShareAsync).toHaveBeenCalledWith('file:///cache/prod-1-timetable-print.pdf', {
      mimeType: 'application/pdf',
      dialogTitle: 'タイムテーブルを共有',
    });
    expect(mockPrintAsync).not.toHaveBeenCalled();
  });

  it('surfaces an API error from the fetch instead of attempting to write a file', async () => {
    const result = await renderReady();

    global.fetch = jest.fn(async () => {
      const body = { code: 'stageart_print_view_access_denied', message: 'Forbidden' };
      return { ok: false, status: 403, json: async () => body, text: async () => JSON.stringify(body) } as Response;
    });

    await expect(
      act(async () => {
        await result.current.print.mutateAsync({ paperSize: 'A4', orientation: 'portrait', action: 'share' });
      })
    ).rejects.toThrow();

    expect(mockWrite).not.toHaveBeenCalled();
  });
});
