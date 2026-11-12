import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react-native';
import { Text } from 'react-native';

import { AuthProvider } from '@/auth/AuthContext';
import { WebSidebarNav } from '@/components/chrome/WebSidebarNav';

import { mockFetchRoutes, orgOne, productionOne } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

let mockPathname = '/home';
jest.mock('expo-router', () => ({
  ...jest.requireActual('expo-router'),
  usePathname: () => mockPathname,
}));

function renderHarness() {
  const queryClient = new QueryClient();
  return render(
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <WebSidebarNav>
          <Text>content</Text>
        </WebSidebarNav>
      </AuthProvider>
    </QueryClientProvider>
  );
}

/**
 * StageArt Phase 1: Web's persistent sidebar, rebuilt around the
 * Context Area design - mirrors NativeDrawerMenu.test.tsx's coverage
 * for the same shared useNavMenu() source.
 */
describe('WebSidebarNav', () => {
  beforeEach(() => {
    mockPathname = '/home';
  });

  it('renders the Fixed Area and Home Context items on /home, with no Context label', async () => {
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [] },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [] },
    ]);

    renderHarness();

    await waitFor(() => expect(screen.getByTestId('web-chrome-nav-discover-organizations')).toBeVisible());
    expect(screen.getByTestId('web-chrome-nav-home')).toBeVisible();
    expect(screen.getByTestId('web-chrome-nav-mypage')).toBeVisible();
    expect(screen.getByTestId('web-chrome-nav-settings')).toBeVisible();
    expect(screen.getByTestId('web-chrome-logout')).toBeVisible();
    expect(screen.queryByTestId('web-chrome-context-label')).toBeNull();
  });

  it('shows a disabled 公演情報 item inside Production Context for a non-manager', async () => {
    mockPathname = `/productions/${productionOne.id}`;
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [orgOne] },
      { test: (url) => url.endsWith(`/productions/${productionOne.id}`), status: 200, body: { ...productionOne, is_primary_manager: false, delegate_role: null } },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [] },
    ]);

    renderHarness();

    await waitFor(() => expect(screen.getByTestId('web-chrome-context-label')).toHaveTextContent(productionOne.name));
    // Disabled items render as plain View (not TouchableOpacity), still discoverable by testID.
    expect(screen.getByTestId('web-chrome-nav-production-info')).toBeVisible();
    expect(screen.getByTestId('web-chrome-nav-production-ticket')).toBeVisible();
  });

  /**
   * StageArt UI再構成 instruction (this round §テスト): "Home：Home
   * Navigationが表示される" - and only Home Navigation, no Organization/
   * Production item ever leaks into the Home Context sidebar.
   */
  it('shows only Home Context items on /home - no Organization/Production item leaks in', async () => {
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [] },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [] },
    ]);

    renderHarness();

    await waitFor(() => expect(screen.getByTestId('web-chrome-nav-discover-organizations')).toBeVisible());
    expect(screen.queryByTestId('web-chrome-nav-organization-info')).toBeNull();
    expect(screen.queryByTestId('web-chrome-nav-production-info')).toBeNull();
    expect(screen.queryByTestId('web-chrome-back-to')).toBeNull();
  });

  /**
   * StageArt UI再構成 instruction (this round §テスト): "Organization：
   * Organization Navigationだけが表示される" - Home/Production items must
   * not appear inside Organization Context.
   */
  it('shows only Organization Context items on /organizations/{id} - no Home/Production item leaks in', async () => {
    mockPathname = `/organizations/${orgOne.id}`;
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [orgOne] },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [] },
    ]);

    renderHarness();

    await waitFor(() => expect(screen.getByTestId('web-chrome-nav-organization-info')).toBeVisible());
    expect(screen.queryByTestId('web-chrome-nav-discover-organizations')).toBeNull();
    expect(screen.queryByTestId('web-chrome-nav-production-info')).toBeNull();
    // Organization Context -> Home の戻る導線 (§「戻る・Context切替」)。
    expect(screen.getByTestId('web-chrome-back-to')).toHaveTextContent(/所属団体一覧へ戻る/);
  });

  /**
   * StageArt UI再構成 instruction (this round §「戻る・Context切替」):
   * a dynamic Production -> Organization backTo link was attempted this
   * round but reverted (see useNavMenu.ts's own ORGANIZATION_BACK_TO
   * docblock and this round's report - it broke ~19 unrelated tests by
   * adding a new async operation to the globally-shared useNavMenu()).
   * Production Context therefore shows no backTo link at all for now,
   * and never mixes in Organization/Home items.
   */
  it('shows only Production Context items on /productions/{id}, with no backTo link', async () => {
    mockPathname = `/productions/${productionOne.id}`;
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [orgOne] },
      { test: (url) => url.endsWith(`/productions/${productionOne.id}`), status: 200, body: productionOne },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
    ]);

    renderHarness();

    await waitFor(() => expect(screen.getByTestId('web-chrome-nav-production-info')).toBeVisible());
    expect(screen.queryByTestId('web-chrome-nav-organization-info')).toBeNull();
    expect(screen.queryByTestId('web-chrome-nav-discover-organizations')).toBeNull();
    expect(screen.queryByTestId('web-chrome-back-to')).toBeNull();
  });
});
