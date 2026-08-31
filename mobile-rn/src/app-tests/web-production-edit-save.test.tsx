import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

import { AuthProvider } from '@/auth/AuthContext';
import { OrganizationProvider } from '@/features/organization/OrganizationContext';

import { mockFetchRoutes, productionOne } from './__fixtures__/homeFixtures';
import ProductionEditScreen from '../app/productions/[id]/edit';

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
 */
jest.mock('expo-router', () => ({
  useRouter: () => ({ push: jest.fn(), replace: mockReplace }),
  useLocalSearchParams: () => ({ id: 'prod-1' }),
}));

function renderEdit() {
  mockFetchRoutes([{ test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne }]);

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
    await waitFor(() => expect(screen.getByTestId('production-edit-name').props.value).toBe('新しい公演名'));

    fireEvent.press(screen.getByTestId('production-edit-submit'));

    await waitFor(() => expect(mockReplace).toHaveBeenCalledWith({ pathname: '/productions/prod-1', params: { saved: '1' } }));

    const putCall = (global.fetch as jest.Mock).mock.calls.find(
      ([url, options]: [string, RequestInit]) => url.includes('/productions/prod-1') && options?.method === 'PUT'
    );
    expect(putCall).toBeDefined();
    const sentBody = JSON.parse(putCall[1].body as string);
    expect(sentBody).toMatchObject({ name: '新しい公演名', title_heading: null });
    expect(sentBody.published).toBeUndefined();
  });
});
