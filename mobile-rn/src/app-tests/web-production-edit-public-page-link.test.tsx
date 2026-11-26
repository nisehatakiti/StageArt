import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react-native';

import { AuthProvider } from '@/auth/AuthContext';
import { OrganizationProvider } from '@/features/organization/OrganizationContext';

import { mockFetchRoutes, orgOne, productionOne, projectOne } from './__fixtures__/homeFixtures';
import ProductionEditScreen from '../app/(app)/productions/[id]/edit';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

jest.mock('expo-router', () => ({
  useRouter: () => ({ push: jest.fn(), replace: jest.fn() }),
  useLocalSearchParams: () => ({ id: 'prod-1' }),
}));

/**
 * StageArt 公開ページ Phase: split out of web-production-edit-save.test.tsx
 * - a second it() in that same describe block was found to fail to find
 * even baseline elements (`production-edit-name`), reproduced in
 * isolation (that file alone), matching this codebase's own documented
 * per-file test-isolation issue (see home-multi-org-switch.test.tsx's
 * own docblock) rather than a real product bug.
 */
describe('Web 公演情報編集: 公開ページ設定', () => {
  it('shows the public-page setting and full development public URL when the Production is published', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [{ ...orgOne, slug: 'kujira' }] },
      { test: (u) => u.endsWith('/projects'), status: 200, body: [projectOne] },
      {
        test: (u) => u.endsWith('/productions/prod-1'),
        status: 200,
        body: { ...productionOne, slug: 'kappa-homerun', published_at: '2026-10-09T09:00:00+09:00' },
      },
    ]);

    const queryClient = new QueryClient();
    render(
      <QueryClientProvider client={queryClient}>
        <AuthProvider>
          <OrganizationProvider>
            <ProductionEditScreen />
          </OrganizationProvider>
        </AuthProvider>
      </QueryClientProvider>
    );

    // Waiting on the card alone is not enough: it renders as soon as
    // `production` loads, before the separate organizations/projects
    // queries behind useProductionOrganization() resolve, so the actual
    // link element - which also needs `organization?.slug` - may not
    // exist yet even once the card is visible.
    await waitFor(() => expect(screen.getByTestId('production-edit-public-page-link')).toBeVisible(), { timeout: 10000 });
    expect(screen.getByTestId('production-edit-published').props.value).toBe(true);
    expect(screen.getByTestId('production-edit-public-page-link')).toHaveTextContent(
      'https://dummy.stageart.top/kujira/kappa-homerun/',
      { exact: false }
    );
  });
});
