import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

import { AuthProvider } from '@/auth/AuthContext';
import { OrganizationProvider } from '@/features/organization/OrganizationContext';

import { mockFetchRoutes } from './__fixtures__/homeFixtures';
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
 * StageArt Phase 1 (OrganizationSetupPolicy.md "Step 2/3"): the 会計 toggle
 * added to Organization creation - confirms the opening-balance fields
 * stay hidden until Accounting is turned on.
 */
describe('CreateOrganizationScreen accounting toggle', () => {
  it('hides the opening balance fields until Accounting is toggled on', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [] },
      { test: (u) => u.endsWith('/productions'), status: 200, body: [] },
      { test: (u) => u.endsWith('/projects'), status: 200, body: [] },
    ]);

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
    expect(screen.queryByTestId('create-organization-cash-balance')).toBeNull();

    fireEvent.press(screen.getByTestId('create-organization-accounting-toggle-row'));

    await waitFor(() => expect(screen.getByTestId('create-organization-cash-balance')).toBeVisible());
    expect(screen.getByTestId('create-organization-bank-balance')).toBeVisible();
  });
});
