import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react-native';

import { AuthProvider } from '@/auth/AuthContext';
import { OrganizationProvider } from '@/features/organization/OrganizationContext';

import { mockFetchRoutes, myDashboardEmpty } from './__fixtures__/homeFixtures';
import OrganizationsListScreen from '../app/(app)/organizations/index';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

jest.mock('expo-router', () => ({
  useRouter: () => ({ push: jest.fn() }),
}));

/**
 * StageArt Blueprint再構成 Phase 1c: renders the single canonical
 * HomeScreen (see web-dashboard-greeting.test.tsx's docblock for why) -
 * asserts the 団体 section's own empty-state prompt, distinct from
 * home-empty-organizations.test.tsx (which asserts the primary nav grid
 * still renders for an unaffiliated Person, not this section's own CTA
 * text).
 */
/*
 * R7: 3113937 (2026-10-06) moved the 団体 list and its empty-state prompt
 * off Home to the 「所属団体」 menu entry (/organizations).
 */
describe('所属団体: 団体（空の場合）', () => {
  it('shows an empty-state prompt to create an Organization when the Person has none', async () => {
    mockFetchRoutes([
      { test: (url) => url.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [] },
    ]);

    const queryClient = new QueryClient();
    render(
      <QueryClientProvider client={queryClient}>
        <AuthProvider>
          <OrganizationProvider>
            <OrganizationsListScreen />
          </OrganizationProvider>
        </AuthProvider>
      </QueryClientProvider>
    );

    await waitFor(() => expect(screen.getByTestId('organizations-list-empty')).toBeVisible());
    expect(screen.getByText(/「団体を作る」から最初の団体を作成してください/)).toBeVisible();
    expect(screen.getByTestId('organizations-create-link')).toBeVisible();
    expect(screen.queryByTestId('organizations-table')).toBeNull();
  });
});
