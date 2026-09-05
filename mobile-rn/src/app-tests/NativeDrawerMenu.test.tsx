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

/** StageArt Blueprint再構成 Phase 1 §27: Native's Hamburger Menu -
 * confirms the basic menu items render and that logout is reachable
 * (and actually confirmed, not fired blind) from inside it, matching
 * the same confirmAlert() pattern already proven for WebLayout/home.tsx
 * logout in the earlier logout-bug fix. */
describe('NativeDrawerMenu', () => {
  it('renders the basic menu items and the logout entry', async () => {
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [] },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [] },
    ]);

    renderHarness();

    await waitFor(() => expect(screen.getByTestId('native-drawer-discover-organizations')).toBeVisible());
    expect(screen.getByTestId('native-drawer-discover-productions')).toBeVisible();
    expect(screen.getByTestId('native-drawer-profile')).toBeVisible();
    expect(screen.getByTestId('native-drawer-account')).toBeVisible();
    expect(screen.getByTestId('native-drawer-logout')).toBeVisible();
  });

  it('includes the 団体情報 admin entry when the Person owns an Organization', async () => {
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [orgOne] },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [] },
    ]);

    renderHarness();

    await waitFor(() => expect(screen.getByTestId('native-drawer-org-admin')).toBeVisible());
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
