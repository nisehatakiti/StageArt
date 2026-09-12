import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import type { PropsWithChildren } from 'react';
import { useState } from 'react';
import { Alert } from 'react-native';

import { AuthProvider } from '@/auth/AuthContext';
import { NativeDrawerMenu } from '@/components/chrome/NativeDrawerMenu';

import { mockFetchRoutes, orgOne } from './__fixtures__/homeFixtures';

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

function Harness() {
  const [visible, setVisible] = useState(true);
  return <NativeDrawerMenu visible={visible} onClose={() => setVisible(false)} />;
}

function renderHarness() {
  const queryClient = new QueryClient();
  return render(
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <Harness />
      </AuthProvider>
    </QueryClientProvider>
  );
}

/**
 * StageArt Phase 1: Native's Hamburger Menu, rebuilt around the Context
 * Area design - confirms the Fixed Area items always render, that Home
 * Context's items render on /home, and that logout is reachable (and
 * actually confirmed, not fired blind).
 */
describe('NativeDrawerMenu', () => {
  beforeEach(() => {
    mockPathname = '/home';
  });

  it('renders the Fixed Area items, Home Context items, and the logout entry', async () => {
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [] },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [] },
    ]);

    renderHarness();

    await waitFor(() => expect(screen.getByTestId('native-drawer-discover-organizations')).toBeVisible());
    expect(screen.getByTestId('native-drawer-discover-productions')).toBeVisible();
    expect(screen.getByTestId('native-drawer-favorites')).toBeVisible();
    expect(screen.getByTestId('native-drawer-my-organizations')).toBeVisible();
    expect(screen.getByTestId('native-drawer-participating-productions')).toBeVisible();
    expect(screen.getByTestId('native-drawer-viewing-history')).toBeVisible();
    expect(screen.getByTestId('native-drawer-home')).toBeVisible();
    expect(screen.getByTestId('native-drawer-mypage')).toBeVisible();
    expect(screen.getByTestId('native-drawer-settings')).toBeVisible();
    expect(screen.getByTestId('native-drawer-logout')).toBeVisible();
    // Home Context shows no Context heading (this is the Fixed Area's own ホーム, not a named Context).
    expect(screen.queryByTestId('native-drawer-context-label')).toBeNull();
  });

  it('shows the Organization name as the Context label inside Organization Context', async () => {
    mockPathname = `/organizations/${orgOne.id}/edit`;
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [orgOne] },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [] },
    ]);

    renderHarness();

    await waitFor(() => expect(screen.getByTestId('native-drawer-context-label')).toHaveTextContent(orgOne.name));
    expect(screen.getByTestId('native-drawer-organization-info')).toBeVisible();
    expect(screen.getByTestId('native-drawer-organization-members')).toBeVisible();
  });

  it('shows a confirmation before logging out, and does nothing if cancelled', async () => {
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [] },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [] },
    ]);

    const alertSpy = jest.spyOn(Alert, 'alert').mockImplementation((title, message, buttons) => {
      expect(title).toBe('ログアウト');
      expect(message).toBe('ログアウトしますか？');
      const cancel = buttons?.find((button) => button.style === 'cancel');
      expect(cancel?.onPress).toBeUndefined();
    });

    renderHarness();

    await waitFor(() => expect(screen.getByTestId('native-drawer-logout')).toBeVisible());
    fireEvent.press(screen.getByTestId('native-drawer-logout'));

    expect(alertSpy).toHaveBeenCalledTimes(1);
  });
});
