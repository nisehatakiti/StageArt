import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react-native';

import { AuthProvider } from '@/auth/AuthContext';
import { OrganizationProvider } from '@/features/organization/OrganizationContext';
import type { Production } from '@/types/api';

import { mockFetchRoutes, orgOne, productionOne, projectOne } from './__fixtures__/homeFixtures';
import ProductionManagementScreen from '../app/(app)/productions/[id]/index';

/** AuthProvider boot (refresh) plus the Production queries can take longer than the default 1s waitFor. */
const FIRST_RENDER = { timeout: 10000 };

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

const mockRouter = { push: jest.fn(), replace: jest.fn(), setParams: jest.fn() };
const mockParams = { id: 'prod-1' };

jest.mock('expo-router', () => ({
  useRouter: () => mockRouter,
  useLocalSearchParams: () => mockParams,
}));

/**
 * 公演管理トップ (`/productions/[id]`) by 担当区分: 公演情報・公開設定・招待管理
 * for the PrimaryManager and the 公演代理人 only; 出演者・参加者 for anyone
 * holding PARTICIPANT_MANAGER. 稽古担当 and 会計担当 get none of them.
 * Rendered directly (not via renderRouter()), as the other multi-test
 * screen suites are, so each test starts from real timers.
 */
function renderAs(fields: Partial<Production>) {
  mockFetchRoutes([
    { test: (u) => u.endsWith('/organizations'), status: 200, body: [orgOne] },
    { test: (u) => u.endsWith('/projects'), status: 200, body: [projectOne] },
    { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: { ...productionOne, ...fields } },
  ]);

  return render(
    <QueryClientProvider client={new QueryClient()}>
      <AuthProvider>
        <OrganizationProvider>
          <ProductionManagementScreen />
        </OrganizationProvider>
      </AuthProvider>
    </QueryClientProvider>
  );
}

function disabled(testID: string): boolean {
  return !!screen.getByTestId(testID).props.accessibilityState?.disabled;
}

function responsibilityText(): string {
  const children = screen.getByTestId('production-management-responsibility').props.children;
  return Array.isArray(children) ? children.join('') : String(children);
}

describe('公演管理トップ: 担当区分ごとのメニュー', () => {
  it('PrimaryManager: every management card, including 招待管理', async () => {
    renderAs({ is_primary_manager: true, delegate_roles: [], is_proxy_manager: false, can_manage_production_operations: true });

    await waitFor(() => expect(screen.getByTestId('production-management-name')).toBeVisible(), FIRST_RENDER);
    expect(disabled('production-management-menu-edit')).toBe(false);
    expect(disabled('production-management-menu-publish')).toBe(false);
    expect(disabled('production-management-menu-participants')).toBe(false);
    expect(screen.getByTestId('production-management-menu-invite')).toBeVisible();
    expect(responsibilityText()).toContain('PrimaryManager');
  });

  it('公演代理人: the same cards as the PrimaryManager', async () => {
    renderAs({
      is_primary_manager: false,
      delegate_role: 'PARTICIPANT_MANAGER',
      delegate_roles: ['PARTICIPANT_MANAGER', 'REHEARSAL_MANAGER'],
      is_proxy_manager: true,
      can_manage_production_operations: true,
    });

    await waitFor(() => expect(screen.getByTestId('production-management-name')).toBeVisible(), FIRST_RENDER);
    expect(disabled('production-management-menu-edit')).toBe(false);
    expect(disabled('production-management-menu-publish')).toBe(false);
    expect(disabled('production-management-menu-participants')).toBe(false);
    expect(screen.getByTestId('production-management-menu-invite')).toBeVisible();
    expect(responsibilityText()).toContain('公演代理人');
  });

  it('稽古担当: no 公演情報 / 公開設定 / 出演者・参加者, no 招待管理', async () => {
    renderAs({
      is_primary_manager: false,
      delegate_role: 'REHEARSAL_MANAGER',
      delegate_roles: ['REHEARSAL_MANAGER'],
      is_proxy_manager: false,
      can_manage_production_operations: false,
    });

    await waitFor(() => expect(screen.getByTestId('production-management-name')).toBeVisible(), FIRST_RENDER);
    expect(disabled('production-management-menu-edit')).toBe(true);
    expect(disabled('production-management-menu-publish')).toBe(true);
    expect(disabled('production-management-menu-participants')).toBe(true);
    expect(screen.queryByTestId('production-management-menu-invite')).toBeNull();
    expect(responsibilityText()).toContain('稽古担当');
  });

  it('会計担当: no Production management card, no 招待管理', async () => {
    renderAs({
      is_primary_manager: false,
      delegate_role: 'ACCOUNTING_MANAGER',
      delegate_roles: ['ACCOUNTING_MANAGER'],
      is_proxy_manager: false,
      can_manage_production_operations: false,
    });

    await waitFor(() => expect(screen.getByTestId('production-management-name')).toBeVisible(), FIRST_RENDER);
    expect(disabled('production-management-menu-edit')).toBe(true);
    expect(disabled('production-management-menu-publish')).toBe(true);
    expect(disabled('production-management-menu-participants')).toBe(true);
    expect(screen.queryByTestId('production-management-menu-invite')).toBeNull();
    expect(responsibilityText()).toContain('会計担当');
  });

  it('PARTICIPANT_MANAGER only: 出演者・参加者 only, no 招待管理', async () => {
    renderAs({
      is_primary_manager: false,
      delegate_role: 'PARTICIPANT_MANAGER',
      delegate_roles: ['PARTICIPANT_MANAGER'],
      is_proxy_manager: false,
      can_manage_production_operations: false,
    });

    await waitFor(() => expect(screen.getByTestId('production-management-name')).toBeVisible(), FIRST_RENDER);
    expect(disabled('production-management-menu-edit')).toBe(true);
    expect(disabled('production-management-menu-publish')).toBe(true);
    expect(disabled('production-management-menu-participants')).toBe(false);
    expect(screen.queryByTestId('production-management-menu-invite')).toBeNull();
  });
});
