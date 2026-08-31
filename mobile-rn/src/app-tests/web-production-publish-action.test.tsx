import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

import { AuthProvider } from '@/auth/AuthContext';
import { OrganizationProvider } from '@/features/organization/OrganizationContext';

import { mockFetchRoutes, orgOne, productionOne, projectOne } from './__fixtures__/homeFixtures';
import ProductionPublishScreen from '../app/productions/[id]/publish';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

const mockPush = jest.fn();

jest.mock('expo-router', () => ({
  useRouter: () => ({ push: mockPush, replace: jest.fn() }),
  useLocalSearchParams: () => ({ id: 'prod-1' }),
}));

/**
 * StageArt Web版 公演管理 Phase: 公開設定 - the "公開する" action itself.
 * Rendered directly (not via renderRouter()) for the same fake-timer
 * reason as the edit-save tests: pressing 公開する fires a mutation whose
 * settle this screen observes via a plain `waitFor`, and this class of
 * interaction was found unreliable under renderRouter()'s forced fake
 * timers elsewhere in this suite.
 *
 * Directly reproduces the previously-reported "公演を作成したあと、下書き
 * から公開に進めない" scenario's happy path: press 公開する -> the real
 * PUT /productions/{id} fires with published:true -> the screen flips to
 * showing the public page link, entirely from the refetched
 * useProduction(id) data (no local "just published" flag).
 */
function renderPublish(production = productionOne) {
  mockFetchRoutes([
    { test: (u) => u.endsWith('/organizations'), status: 200, body: [orgOne] },
    { test: (u) => u.endsWith('/projects'), status: 200, body: [projectOne] },
    { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: production },
  ]);

  const queryClient = new QueryClient();
  return render(
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <OrganizationProvider>
          <ProductionPublishScreen />
        </OrganizationProvider>
      </AuthProvider>
    </QueryClientProvider>
  );
}

describe('Web 公開設定: 下書きから公開する', () => {
  it('shows 下書き, and pressing 公開する calls the real publish endpoint with the current name/title_heading', async () => {
    renderPublish();

    await waitFor(() => expect(screen.getByTestId('production-publish-status-pill')).toBeVisible());
    expect(screen.getByText('下書き（未公開）')).toBeVisible();

    fireEvent.press(screen.getByTestId('production-publish-button'));

    await waitFor(() => {
      const putCall = (global.fetch as jest.Mock).mock.calls.find(
        ([url, options]: [string, RequestInit]) => url.endsWith('/productions/prod-1') && options?.method === 'PUT'
      );
      expect(putCall).toBeDefined();
      const sentBody = JSON.parse(putCall[1].body as string);
      expect(sentBody).toMatchObject({ name: productionOne.name, title_heading: null, published: true });
    });
  });
});
