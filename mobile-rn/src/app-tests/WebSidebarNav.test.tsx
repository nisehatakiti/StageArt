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
});
