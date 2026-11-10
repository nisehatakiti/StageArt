import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react-native';

import { AuthProvider } from '@/auth/AuthContext';
import { OrganizationProvider } from '@/features/organization/OrganizationContext';

import { mockFetchRoutes, myDashboardEmpty, orgOne, orgTwo, productionOne, productionTwo, projectOne, projectTwo } from './__fixtures__/homeFixtures';
import OrganizationsListScreen from '../app/(app)/organizations/index';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

const mockPush = jest.fn();

jest.mock('expo-router', () => ({
  useRouter: () => ({ push: mockPush }),
}));

/**
 * StageArt Blueprint再構成 Phase 1c: renders the single canonical
 * HomeScreen (see web-dashboard-greeting.test.tsx's docblock for why).
 *
 * StageArt仕様遵守 (2026-09-19): this card previously also rendered a
 * "公開中"/"下書き" status pill derived from `organization.published_at`
 * - removed because no Blueprint doc defines a normal "not yet public"
 * Organization state (`03-PublicPageURLAndPublicationSchedule.md`:
 * "Organizationの作成・団体情報の保存に、非公開状態を設けない。保存が成功
 * したOrganizationは保存と同時に公開状態とする"), so that implementation-
 * only pill had no basis in spec. This test no longer asserts on it.
 */
/*
 * R7: 3113937 (2026-10-06, "simplify dashboard") moved this list off Home
 * to the 「所属団体」 menu entry (/organizations). The list shows each
 * Organization's name, role and Production count, and a row opens the
 * Organization - the same information this test checked on Home.
 */
describe('所属団体: 団体（参加中／管理中）', () => {
  it('lists each Organization with its Role and own Productions, and tapping navigates to its management screen', async () => {
    mockFetchRoutes([
      { test: (url) => url.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [orgOne, orgTwo] },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [productionOne, productionTwo] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [projectOne, projectTwo] },
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

    await waitFor(() => expect(screen.getByTestId('organizations-row-org-1')).toBeVisible());
    const orgOneRow = within(screen.getByTestId('organizations-row-org-1'));
    expect(orgOneRow.getByText('○○演劇団')).toBeVisible();
    expect(orgOneRow.getByText('オーナー')).toBeVisible();
    expect(within(screen.getByTestId('organizations-row-org-2')).getByText('メンバー')).toBeVisible();

    // Its own Productions (org-2's are not counted here).
    await waitFor(() => expect(orgOneRow.getByText('公演 1件')).toBeVisible());

    fireEvent.press(screen.getByTestId('organizations-row-org-1'));
    expect(mockPush).toHaveBeenCalledWith('/organizations/org-1');
  });
});
