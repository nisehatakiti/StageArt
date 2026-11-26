import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { renderHook, waitFor } from '@testing-library/react-native';
import type { PropsWithChildren } from 'react';

import { AuthProvider } from '@/auth/AuthContext';
import { useNavMenu } from '@/components/chrome/useNavMenu';

import { mockFetchRoutes, productionOne } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

let mockPathname = '/home';
jest.mock('expo-router', () => ({
  ...jest.requireActual('expo-router'),
  usePathname: () => mockPathname,
}));

function wrapper({ children }: PropsWithChildren) {
  return (
    <QueryClientProvider client={new QueryClient()}>
      <AuthProvider>{children}</AuthProvider>
    </QueryClientProvider>
  );
}

const FIRST_RENDER = { timeout: 10000 };

/** Keys whose availability this test checks, in the order below. */
const KEYS = [
  'production-info',
  'production-members',
  'production-settlement',
  'production-accounting-budget',
  'production-accounting',
] as const;

type Enabled = Record<(typeof KEYS)[number], boolean>;

/**
 * PR #8 統合 (2026-11-24): the Production menu by 担当区分.
 * - 公演情報: PrimaryManager or 公演代理人
 * - メンバー管理: PrimaryManager or any PARTICIPANT_MANAGER (公演代理人 included)
 * - 公演終了／精算処理, 会計 (予算・実績): PrimaryManager, 公演代理人 or 会計担当
 *   (the 公演の完了 button inside stays PrimaryManager-only - see
 *   web-production-proxy-accounting.test.tsx)
 */
const CASES: [string, Partial<typeof productionOne>, Enabled][] = [
  [
    'PrimaryManager',
    { is_primary_manager: true, delegate_role: null, delegate_roles: [] },
    { 'production-info': true, 'production-members': true, 'production-settlement': true, 'production-accounting-budget': true, 'production-accounting': true },
  ],
  [
    '公演代理人',
    { is_primary_manager: false, delegate_role: 'PARTICIPANT_MANAGER', delegate_roles: ['PARTICIPANT_MANAGER', 'REHEARSAL_MANAGER'] },
    { 'production-info': true, 'production-members': true, 'production-settlement': true, 'production-accounting-budget': true, 'production-accounting': true },
  ],
  [
    '会計担当',
    { is_primary_manager: false, delegate_role: 'ACCOUNTING_MANAGER', delegate_roles: ['ACCOUNTING_MANAGER'] },
    { 'production-info': false, 'production-members': false, 'production-settlement': true, 'production-accounting-budget': true, 'production-accounting': true },
  ],
  [
    '稽古担当',
    { is_primary_manager: false, delegate_role: 'REHEARSAL_MANAGER', delegate_roles: ['REHEARSAL_MANAGER'] },
    { 'production-info': false, 'production-members': false, 'production-settlement': false, 'production-accounting-budget': false, 'production-accounting': false },
  ],
];

describe('useNavMenu: Production menu by 担当区分 (PR #8 統合)', () => {
  it.each(CASES)('%s', async (_label, fields, expected) => {
    const production = { ...productionOne, ...fields };
    mockPathname = `/productions/${production.id}`;
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [] },
      { test: (url) => url.endsWith(`/productions/${production.id}`), status: 200, body: production },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [] },
    ]);

    const { result } = await renderHook(() => useNavMenu(), { wrapper });

    await waitFor(() => expect(result.current.contextLabel).toBe(production.name), FIRST_RENDER);
    const enabled = Object.fromEntries(
      KEYS.map((key) => {
        const item = result.current.contextItems.find((candidate) => candidate.key === key);
        return [key, !!item && !item.disabled];
      })
    );
    expect(enabled).toEqual(expected);
  });
});
