import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react-native';
import type { ComponentType } from 'react';

import { AuthProvider } from '@/auth/AuthContext';
import { OrganizationProvider } from '@/features/organization/OrganizationContext';
import type { Production } from '@/types/api';

import { mockFetchRoutes, orgOne, productionOne, projectOne } from './__fixtures__/homeFixtures';
import ProductionEditScreen from '../app/(app)/productions/[id]/edit';
import ProductionManagementScreen from '../app/(app)/productions/[id]/index';
import ProductionPublishScreen from '../app/(app)/productions/[id]/publish';
import ProductionSettlementScreen from '../app/(app)/productions/[id]/settlement';

/** AuthProvider boot (refresh) plus the Production queries can take longer than the default 1s waitFor. */
const FIRST_RENDER = { timeout: 10000 };

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

const mockRouter = { push: jest.fn(), replace: jest.fn(), setParams: jest.fn(), back: jest.fn() };
const mockParams = { id: 'prod-1' };

jest.mock('expo-router', () => ({
  ...jest.requireActual('expo-router'),
  useRouter: () => mockRouter,
  useLocalSearchParams: () => mockParams,
}));

const PRIMARY_MANAGER: Partial<Production> = { is_primary_manager: true, delegate_role: null, delegate_roles: [] };
const PROXY_MANAGER: Partial<Production> = {
  is_primary_manager: false,
  delegate_role: 'PARTICIPANT_MANAGER',
  delegate_roles: ['PARTICIPANT_MANAGER', 'REHEARSAL_MANAGER'],
};
const ACCOUNTING_MANAGER: Partial<Production> = {
  is_primary_manager: false,
  delegate_role: 'ACCOUNTING_MANAGER',
  delegate_roles: ['ACCOUNTING_MANAGER'],
};
const REHEARSAL_MANAGER: Partial<Production> = {
  is_primary_manager: false,
  delegate_role: 'REHEARSAL_MANAGER',
  delegate_roles: ['REHEARSAL_MANAGER'],
};

const settlementSummary = {
  members: [
    {
      person_id: 'person-2',
      display_name: '山田太郎',
      confirmed_ticket_back_amount: 300,
      already_settled_amount: 0,
      outstanding_amount: 300,
      last_settled_amount: 0,
    },
  ],
  quota_shortfall_count: 0,
  quota_shortfall_payable: 0,
};

/**
 * PR #8 統合 (2026-11-24): what each 担当区分 sees on the Production screens.
 * Rendered directly (not via renderRouter()), as the other multi-test
 * screen suites are, so each test starts from real timers.
 */
function renderAs(Screen: ComponentType, fields: Partial<Production>) {
  mockFetchRoutes([
    { test: (u) => u.endsWith('/organizations'), status: 200, body: [orgOne] },
    { test: (u) => u.endsWith('/projects'), status: 200, body: [projectOne] },
    { test: (u) => u.endsWith('/productions/prod-1/settlement'), status: 200, body: settlementSummary },
    { test: (u) => u.endsWith('/productions/prod-1/performances'), status: 200, body: [] },
    { test: (u) => u.includes('/productions/prod-1/ticket-sales-settings'), status: 200, body: {} },
    { test: (u) => u.includes('/productions/prod-1/participation-requests'), status: 200, body: [] },
    { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: { ...productionOne, ...fields } },
  ]);

  return render(
    <QueryClientProvider client={new QueryClient()}>
      <AuthProvider>
        <OrganizationProvider>
          <Screen />
        </OrganizationProvider>
      </AuthProvider>
    </QueryClientProvider>
  );
}

describe('公演終了／精算処理: 精算 is open to three, 公演の完了 to the PrimaryManager only', () => {
  it.each([
    ['公演代理人', PROXY_MANAGER],
    ['会計担当', ACCOUNTING_MANAGER],
  ])('%s: settles members but gets no 公演の完了 button', async (_label, fields) => {
    renderAs(ProductionSettlementScreen, fields);

    await waitFor(() => expect(screen.getByTestId('settlement-checkbox-person-2')).toBeVisible(), FIRST_RENDER);
    expect(screen.queryByTestId('production-settlement-forbidden')).toBeNull();
    expect(screen.queryByTestId('production-settlement-complete')).toBeNull();
  });

  it('PrimaryManager: settles members and sees the 公演の完了 button', async () => {
    renderAs(ProductionSettlementScreen, PRIMARY_MANAGER);

    await waitFor(() => expect(screen.getByTestId('settlement-checkbox-person-2')).toBeVisible(), FIRST_RENDER);
    expect(screen.getByTestId('production-settlement-complete')).toBeVisible();
  });

  it('稽古担当: refused', async () => {
    renderAs(ProductionSettlementScreen, REHEARSAL_MANAGER);

    await waitFor(() => expect(screen.getByTestId('production-settlement-forbidden')).toBeVisible(), FIRST_RENDER);
  });
});

describe('公演情報・公開設定: PrimaryManager or 公演代理人', () => {
  it.each([
    ['公演情報', ProductionEditScreen, 'production-edit-forbidden'],
    ['公開設定', ProductionPublishScreen, 'production-publish-forbidden'],
  ] as const)('%s: 会計担当 is refused', async (_label, Screen, forbiddenId) => {
    renderAs(Screen, ACCOUNTING_MANAGER);
    await waitFor(() => expect(screen.getByTestId(forbiddenId)).toBeVisible(), FIRST_RENDER);
  });

  it.each([
    ['公演情報', ProductionEditScreen, 'production-edit-name', 'production-edit-forbidden'],
    ['公開設定', ProductionPublishScreen, 'production-publish-status-pill', 'production-publish-forbidden'],
  ] as const)('%s: 公演代理人 gets the screen', async (_label, Screen, contentId, forbiddenId) => {
    renderAs(Screen, PROXY_MANAGER);
    await waitFor(() => expect(screen.getByTestId(contentId)).toBeVisible(), FIRST_RENDER);
    expect(screen.queryByTestId(forbiddenId)).toBeNull();
  });
});

describe('公演管理トップ: 担当区分 and the cards', () => {
  function disabled(testID: string): boolean {
    return !!screen.getByTestId(testID).props.accessibilityState?.disabled;
  }

  function responsibilityText(): string {
    const children = screen.getByTestId('production-management-responsibility').props.children;
    return Array.isArray(children) ? children.join('') : String(children);
  }

  it('公演代理人: 公開設定 and 会計, no lifecycle actions (公演の完了 included)', async () => {
    renderAs(ProductionManagementScreen, PROXY_MANAGER);

    await waitFor(() => expect(screen.getByTestId('production-management-name')).toBeVisible(), FIRST_RENDER);
    expect(disabled('production-management-menu-publish')).toBe(false);
    expect(disabled('production-management-menu-accounting')).toBe(false);
    expect(screen.queryByTestId('production-management-complete')).toBeNull();
    expect(responsibilityText()).toContain('公演代理人');
  });

  it('会計担当: 会計 only', async () => {
    renderAs(ProductionManagementScreen, ACCOUNTING_MANAGER);

    await waitFor(() => expect(screen.getByTestId('production-management-name')).toBeVisible(), FIRST_RENDER);
    expect(disabled('production-management-menu-publish')).toBe(true);
    expect(disabled('production-management-menu-accounting')).toBe(false);
    expect(screen.queryByTestId('production-management-complete')).toBeNull();
    expect(responsibilityText()).toContain('会計担当');
  });

  it('稽古担当: neither 公開設定 nor 会計', async () => {
    renderAs(ProductionManagementScreen, REHEARSAL_MANAGER);

    await waitFor(() => expect(screen.getByTestId('production-management-name')).toBeVisible(), FIRST_RENDER);
    expect(disabled('production-management-menu-publish')).toBe(true);
    expect(disabled('production-management-menu-accounting')).toBe(true);
    expect(responsibilityText()).toContain('稽古担当');
  });

  it('PrimaryManager: every card and the 公演の完了 action', async () => {
    renderAs(ProductionManagementScreen, PRIMARY_MANAGER);

    await waitFor(() => expect(screen.getByTestId('production-management-name')).toBeVisible(), FIRST_RENDER);
    expect(disabled('production-management-menu-publish')).toBe(false);
    expect(disabled('production-management-menu-accounting')).toBe(false);
    expect(screen.getByTestId('production-management-complete')).toBeVisible();
    expect(responsibilityText()).toContain('PrimaryManager');
  });
});
