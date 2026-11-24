import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import type { ReactElement } from 'react';

import { AuthProvider } from '@/auth/AuthContext';
import { OrganizationProvider } from '@/features/organization/OrganizationContext';
import type { Production } from '@/types/api';

import { mockFetchRoutes, orgOne, productionOne, projectOne } from './__fixtures__/homeFixtures';
import ProductionInviteScreen from '../app/(app)/production-invite/[id]';
import ProductionEditScreen from '../app/(app)/productions/[id]/edit';
import ProductionPublishScreen from '../app/(app)/productions/[id]/publish';

/** AuthProvider boot (refresh) plus the Production queries take a few seconds here; the default 1s waitFor is too short. */
const FIRST_RENDER = { timeout: 10000 };

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

// One stable router object (a new object per render can re-trigger effects that depend on it).
const mockRouter = { push: jest.fn(), replace: jest.fn() };
const mockParams = { id: 'prod-1' };

jest.mock('expo-router', () => ({
  useRouter: () => mockRouter,
  useLocalSearchParams: () => mockParams,
}));

const proxy: Partial<Production> = {
  is_primary_manager: false,
  delegate_role: 'PARTICIPANT_MANAGER',
  delegate_roles: ['PARTICIPANT_MANAGER', 'REHEARSAL_MANAGER'],
  is_proxy_manager: true,
  can_manage_production_operations: true,
};

const rehearsalOnly: Partial<Production> = {
  is_primary_manager: false,
  delegate_role: 'REHEARSAL_MANAGER',
  delegate_roles: ['REHEARSAL_MANAGER'],
  is_proxy_manager: false,
  can_manage_production_operations: false,
};

const accountingOnly: Partial<Production> = {
  is_primary_manager: false,
  delegate_role: 'ACCOUNTING_MANAGER',
  delegate_roles: ['ACCOUNTING_MANAGER'],
  is_proxy_manager: false,
  can_manage_production_operations: false,
};

function renderScreen(element: ReactElement, fields: Partial<Production>) {
  mockFetchRoutes([
    { test: (u) => u.endsWith('/organizations'), status: 200, body: [orgOne] },
    { test: (u) => u.endsWith('/projects'), status: 200, body: [projectOne] },
    { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: { ...productionOne, ...fields } },
    { test: (u) => u.endsWith('/productions/prod-1/join-keys'), status: 200, body: [] },
    { test: (u) => u.endsWith('/productions/prod-1/participation-requests'), status: 200, body: [] },
  ]);

  return render(
    <QueryClientProvider client={new QueryClient()}>
      <AuthProvider>
        <OrganizationProvider>{element}</OrganizationProvider>
      </AuthProvider>
    </QueryClientProvider>
  );
}

function putBody(): Record<string, unknown> | undefined {
  const call = (global.fetch as jest.Mock).mock.calls.find(
    ([url, options]: [string, RequestInit]) => url.endsWith('/productions/prod-1') && options?.method === 'PUT'
  );
  return call ? JSON.parse(call[1].body as string) : undefined;
}

describe('公演代理人: 公演情報・公開設定・招待管理の画面', () => {
  it('公演代理人 can open 公演情報 and send a Slug change', async () => {
    renderScreen(<ProductionEditScreen />, proxy);

    // Wait until the form has been filled from the loaded Production, so
    // the change below is not overwritten by that initialisation.
    await waitFor(() => expect(screen.getByTestId('production-edit-name').props.value).toBe(productionOne.name), FIRST_RENDER);
    expect(screen.queryByTestId('production-edit-forbidden')).toBeNull();

    fireEvent.changeText(screen.getByTestId('production-edit-slug'), 'proxy-slug');
    await waitFor(() => expect(screen.getByTestId('production-edit-slug').props.value).toBe('proxy-slug'));
    fireEvent.press(screen.getByTestId('production-edit-submit'));

    await waitFor(() => expect(putBody()).toMatchObject({ slug: 'proxy-slug' }), FIRST_RENDER);
  });

  it('公演代理人 can open 公開設定 and publish', async () => {
    renderScreen(<ProductionPublishScreen />, proxy);

    await waitFor(() => expect(screen.getByTestId('production-publish-button')).toBeVisible(), FIRST_RENDER);
    expect(screen.queryByTestId('production-publish-forbidden')).toBeNull();

    fireEvent.press(screen.getByTestId('production-publish-button'));

    await waitFor(() => expect(putBody()).toMatchObject({ published: true }), FIRST_RENDER);
  });

  it('公演代理人 sees 招待管理 (参加コード)', async () => {
    renderScreen(<ProductionInviteScreen />, proxy);

    await waitFor(() => expect(screen.getByTestId('production-join-key-issue')).toBeVisible(), FIRST_RENDER);
    expect(screen.queryByTestId('production-invite-forbidden')).toBeNull();
  });

  it.each([
    ['稽古担当', rehearsalOnly],
    ['会計担当', accountingOnly],
  ])('%s is told 公演情報 is not available and sends nothing', async (_label, fields) => {
    renderScreen(<ProductionEditScreen />, fields);

    await waitFor(() => expect(screen.getByTestId('production-edit-forbidden')).toBeVisible(), FIRST_RENDER);
    expect(screen.queryByTestId('production-edit-submit')).toBeNull();
    expect(putBody()).toBeUndefined();
  });

  it.each([
    ['稽古担当', rehearsalOnly],
    ['会計担当', accountingOnly],
  ])('%s is told 公開設定 is not available', async (_label, fields) => {
    renderScreen(<ProductionPublishScreen />, fields);

    await waitFor(() => expect(screen.getByTestId('production-publish-forbidden')).toBeVisible(), FIRST_RENDER);
    expect(screen.queryByTestId('production-publish-button')).toBeNull();
  });

  it.each([
    ['稽古担当', rehearsalOnly],
    ['会計担当', accountingOnly],
  ])('%s is told 招待管理 is not available', async (_label, fields) => {
    renderScreen(<ProductionInviteScreen />, fields);

    await waitFor(() => expect(screen.getByTestId('production-invite-forbidden')).toBeVisible(), FIRST_RENDER);
    expect(screen.queryByTestId('production-join-key-issue')).toBeNull();
  });
});
