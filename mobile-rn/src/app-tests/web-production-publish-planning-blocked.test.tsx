import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react-native';

import { AuthProvider } from '@/auth/AuthContext';
import { OrganizationProvider } from '@/features/organization/OrganizationContext';

import { mockFetchRoutes, orgOne, projectOne } from './__fixtures__/homeFixtures';
import ProductionPublishScreen from '../app/(app)/productions/[id]/publish';

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

const planningProduction = {
  id: 'prod-1',
  project_id: 'proj-1',
  name: '○○公演2026',
  title_heading: null,
  status: 'PLANNING',
  slug: 'prod-1-slug',
  published_at: null,
  primary_manager_person_id: 'person-1',
  created_at: '',
  updated_at: '',
  is_primary_manager: true,
  delegate_role: null,
  description: null,
  description_published_at: null,
  flyer_url: null,
  flyer_published_at: null,
  venue_name: null,
  venue_published_at: null,
  schedule_start_date: null,
  schedule_end_date: null,
  schedule_published_at: null,
  script_credit: null,
  direction_credit: null,
  script_direction_published_at: null,
  member_info_published_at: null,
  capacity: 100,
  performance_common_remarks: null,
};

/**
 * StageArt Production Lifecycle整理 instruction (this round): "PLANNING
 * 中に通常の公開切替API/UIからProductionを公開できないようにし" -
 * this screen's normal "公開する" button (which PUTs `published: true`)
 * must not be offered for a PLANNING Production, since the Backend now
 * always rejects that (Production::publish()'s PLANNING Guard). Rendered
 * directly (not via renderRouter()), matching
 * web-production-publish-action.test.tsx's own established reasoning for
 * this screen.
 */
describe('Web 公開設定: PLANNING中は通常の公開切替を提示しない', () => {
  it('hides the 公開する button for a PLANNING Production and explains 公演を確定する instead', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [orgOne] },
      { test: (u) => u.endsWith('/projects'), status: 200, body: [projectOne] },
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: planningProduction },
    ]);

    const queryClient = new QueryClient();
    render(
      <QueryClientProvider client={queryClient}>
        <AuthProvider>
          <OrganizationProvider>
            <ProductionPublishScreen />
          </OrganizationProvider>
        </AuthProvider>
      </QueryClientProvider>
    );

    await waitFor(() => expect(screen.getByTestId('production-publish-status-pill')).toBeVisible());
    expect(screen.getByText('未公開')).toBeVisible();

    expect(screen.queryByTestId('production-publish-button')).toBeNull();
    expect(screen.getByTestId('production-publish-requires-confirmation')).toBeVisible();
  });
});
