import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty, orgOne, productionOne, productionTwo, projectOne } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * StageArt Web版 団体管理 Phase: 公演一覧
 * (`/organizations/[id]/productions` - previously only
 * `/organizations/[id]/productions/create` existed). Reuses
 * useOrganizationProductions() unchanged. `productionOne` here is
 * published with a real Organization+Production slug (its public page
 * link should render); `productionTwo` is unpublished (no public link).
 */
describe('Web 公演一覧', () => {
  it('lists Productions with name/status/公開状態, links to the public page only when published, and links to create', async () => {
    const publishedOrg = { ...orgOne, slug: 'kujira-theatre' };
    const published = { ...productionOne, slug: 'summer-show', published_at: '2026-08-01T00:00:00+09:00' };
    // Both Productions belong to org-1's own (single) Project, so both
    // pass useOrganizationProductions()'s client-side org-scoping filter.
    const draft = { ...productionTwo, project_id: projectOne.id };

    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [publishedOrg] },
      { test: (u) => u.endsWith('/projects'), status: 200, body: [projectOne] },
      { test: (u) => u.endsWith('/productions'), status: 200, body: [published, draft] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/organizations/org-1/productions' });

    await waitFor(() => expect(screen.getByTestId('organization-production-row-prod-1')).toBeVisible());
    expect(screen.getByText('公開中')).toBeVisible();
    expect(screen.getByTestId('organization-production-view-public-prod-1')).toBeVisible();

    expect(screen.getByTestId('organization-production-row-prod-2')).toBeVisible();
    expect(screen.getByText('下書き')).toBeVisible();
    expect(screen.queryByTestId('organization-production-view-public-prod-2')).toBeNull();

    fireEvent.press(screen.getByTestId('organization-productions-create-link'));
    await waitFor(() => expect(screen.getByTestId('create-production-name')).toBeVisible());
  });
});
