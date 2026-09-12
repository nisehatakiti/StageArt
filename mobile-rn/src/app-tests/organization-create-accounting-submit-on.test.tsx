import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

import { AuthProvider } from '@/auth/AuthContext';
import { OrganizationProvider } from '@/features/organization/OrganizationContext';

import { mockFetchRoutes, orgOne } from './__fixtures__/homeFixtures';
import CreateOrganizationScreen from '../app/(app)/organizations/create';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

jest.mock('expo-router', () => ({
  useRouter: () => ({ push: jest.fn(), replace: jest.fn(), setParams: jest.fn() }),
  useLocalSearchParams: () => ({}),
}));

/**
 * `mockFetchRoutes` matches by URL only, but POST /organizations (create)
 * and GET /organizations (this screen's own createdId-restore lookup, run
 * on every mount) hit the exact same URL - this wraps the resulting mock
 * with one extra, Method-aware branch for `/organizations` specifically
 * (matches web-organization-edit-save.test.tsx's own PUT-vs-GET
 * distinction technique for the same reason).
 */
function mockOrganizationsCreate(createdBody: unknown) {
  mockFetchRoutes([
    { test: (u) => u.endsWith('/productions'), status: 200, body: [] },
    { test: (u) => u.endsWith('/projects'), status: 201, body: { id: 'proj-1' } },
  ]);

  const baseFetch = global.fetch as jest.Mock;
  global.fetch = jest.fn(async (input: unknown, init?: RequestInit) => {
    const url = String(input);
    const method = (init?.method ?? 'GET').toUpperCase();

    if (url.endsWith('/organizations') && method === 'POST') {
      return { ok: true, status: 201, text: async () => JSON.stringify(createdBody), json: async () => createdBody } as Response;
    }

    if (url.endsWith('/organizations') && method === 'GET') {
      return { ok: true, status: 200, text: async () => JSON.stringify([]), json: async () => [] } as Response;
    }

    return baseFetch(input, init);
  });
}

/** StageArt Phase 1 (OrganizationSetupPolicy.md "Step 2/3"): submitting
 * with Accounting on sends the opening balances the backend expects. */
describe('CreateOrganizationScreen submit with Accounting on', () => {
  it('submits accounting_enabled and the opening balances', async () => {
    mockOrganizationsCreate({ ...orgOne, accounting_enabled: true });

    const queryClient = new QueryClient();
    render(
      <QueryClientProvider client={queryClient}>
        <AuthProvider>
          <OrganizationProvider>
            <CreateOrganizationScreen />
          </OrganizationProvider>
        </AuthProvider>
      </QueryClientProvider>
    );

    await waitFor(() => expect(screen.getByTestId('create-organization-accounting-toggle')).toBeVisible());
    fireEvent.changeText(screen.getByTestId('create-organization-name'), 'テスト団体');
    await waitFor(() => expect(screen.getByTestId('create-organization-name').props.value).toBe('テスト団体'));

    fireEvent.press(screen.getByTestId('create-organization-accounting-toggle-row'));
    await waitFor(() => expect(screen.getByTestId('create-organization-cash-balance')).toBeVisible());

    fireEvent.changeText(screen.getByTestId('create-organization-cash-balance'), '10000');
    await waitFor(() => expect(screen.getByTestId('create-organization-cash-balance').props.value).toBe('10000'));

    fireEvent.changeText(screen.getByTestId('create-organization-bank-balance'), '50000');
    await waitFor(() => expect(screen.getByTestId('create-organization-bank-balance').props.value).toBe('50000'));
    await waitFor(() => expect(screen.getByTestId('create-organization-submit').props.accessibilityState?.disabled).toBe(false));

    fireEvent.press(screen.getByTestId('create-organization-submit'));

    await waitFor(() => {
      const call = (global.fetch as jest.Mock).mock.calls.find(
        ([url, init]: [string, RequestInit?]) => url.endsWith('/organizations') && init?.method === 'POST'
      );
      expect(call).toBeDefined();
      const body = JSON.parse(call![1].body as string);
      expect(body.accounting_enabled).toBe(true);
      expect(body.opening_cash_balance).toBe(10000);
      expect(body.opening_bank_balance).toBe(50000);
    });
  });
});
