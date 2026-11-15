import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

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

const mockReplace = jest.fn();

/**
 * StageArt Web版 公演管理 Phase: 公演情報編集 - rendered directly (not via
 * renderRouter()) for the same reason as
 * web-organization-edit-save.test.tsx: renderRouter()'s forced Jest fake
 * timers were found to silently drop a fireEvent.changeText-driven
 * useState update in this environment (disclosed already in
 * home-multi-org-switch.test.tsx's own docblock); this text-input flow
 * needs the workaround, every other production screen in this Phase
 * does not.
 *
 * StageArt 公開ページ Phase: this file now covers only the one scenario
 * ("saves changes"); the 公開ページ-card scenarios moved to their own
 * files (web-production-edit-public-page-link.test.tsx,
 * web-production-edit-published-at-preserved.test.tsx) after confirming
 * empirically that a second/third it() in this same describe block fails
 * to find even baseline elements (`production-edit-name`) - reproduced
 * in isolation (this file alone, no other suite running), so it is the
 * established per-file test-isolation issue this codebase already works
 * around elsewhere (see home-multi-org-switch.test.tsx's own docblock),
 * not a real product bug.
 */
jest.mock('expo-router', () => ({
  useRouter: () => ({ push: jest.fn(), replace: mockReplace }),
  useLocalSearchParams: () => ({ id: 'prod-1' }),
}));

function renderEdit(production = productionOne) {
  mockFetchRoutes([
    { test: (u) => u.endsWith('/organizations'), status: 200, body: [{ ...orgOne, slug: 'kujira' }] },
    { test: (u) => u.endsWith('/projects'), status: 200, body: [projectOne] },
    { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: production },
  ]);

  const queryClient = new QueryClient();
  return render(
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <OrganizationProvider>
          <ProductionEditScreen />
        </OrganizationProvider>
      </AuthProvider>
    </QueryClientProvider>
  );
}

describe('Web 公演情報編集: PrimaryManager saves changes', () => {
  beforeEach(() => {
    mockReplace.mockClear();
  });

  it('pre-fills the form, sends name+title_heading unconditionally on save, and redirects to the management top with a saved confirmation', async () => {
    renderEdit();

    await waitFor(() => expect(screen.getByTestId('production-edit-name')).toBeVisible());
    expect(screen.getByTestId('production-edit-name').props.value).toBe('○○公演2026');

    fireEvent.changeText(screen.getByTestId('production-edit-name'), '新しい公演名');
    fireEvent.changeText(screen.getByTestId('production-edit-performance-duration-minutes'), '120');
    await waitFor(() => expect(screen.getByTestId('production-edit-name').props.value).toBe('新しい公演名'));

    fireEvent.press(screen.getByTestId('production-edit-submit'));

    await waitFor(() => expect(mockReplace).toHaveBeenCalledWith({ pathname: '/productions/prod-1', params: { saved: '1' } }));

    const putCall = (global.fetch as jest.Mock).mock.calls.find(
      ([url, options]: [string, RequestInit]) => url.includes('/productions/prod-1') && options?.method === 'PUT'
    );
    expect(putCall).toBeDefined();
    const sentBody = JSON.parse(putCall[1].body as string);
    expect(sentBody).toMatchObject({ name: '新しい公演名', title_heading: null, performance_duration_minutes: 120 });
    expect(sentBody.published).toBe(false);
    expect(sentBody.published_at).toBeNull();
  });
});
