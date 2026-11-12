import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

import { AuthProvider } from '@/auth/AuthContext';
import { OrganizationProvider } from '@/features/organization/OrganizationContext';

import { mockFetchRoutes, orgOne } from './__fixtures__/homeFixtures';
import OrganizationEditScreen from '../app/(app)/organizations/[id]/edit';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

jest.mock('expo-router', () => ({
  useRouter: () => ({ push: jest.fn(), replace: jest.fn() }),
  useLocalSearchParams: () => ({ id: 'org-1' }),
}));

jest.mock('expo-image-picker', () => ({
  requestMediaLibraryPermissionsAsync: jest.fn(async () => ({ granted: true })),
  launchImageLibraryAsync: jest.fn(async () => ({
    canceled: false,
    assets: [{ uri: 'file:///mock-logo.jpg', fileName: 'mock-logo.jpg' }],
  })),
}));

const NEW_LOGO_URL = 'https://dev-api.stageart.top/wp-content/uploads/2026/10/logo-abc123.jpg';

/**
 * StageArt Organization ロゴ保存バグ対策: verifies the race-condition fix
 * in useOrganizationLogo - an upload's onSuccess must write the returned
 * Organization directly into the `organizations` query cache
 * (queryClient.setQueryData), not merely invalidateQueries() it, so the
 * preview and any immediately-following 基本情報 save both see the new
 * logo_url without depending on a background refetch completing first.
 */
describe('Web 団体情報編集: 団体ロゴ', () => {
  it('shows the empty-logo message, then uploads and reflects the new logo synchronously (no network re-fetch needed)', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations/org-1'), status: 200, body: orgOne },
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [orgOne] },
      { test: (u) => u.endsWith('/organizations/org-1/sns'), status: 200, body: [] },
      { test: (u) => u.endsWith('/organizations/org-1/external-links'), status: 200, body: [] },
      {
        test: (u) => u.endsWith('/organizations/org-1/logo'),
        status: 200,
        body: { ...orgOne, logo_url: NEW_LOGO_URL },
      },
    ]);

    // mockFetchRoutes() installs a route-matcher global.fetch that
    // already covers /auth/refresh, /me, etc. (DEFAULT_ROUTES) - wrap it
    // rather than replace it, so this test only has to special-case the
    // asset-URI "download" fetch() the logo picker does for itself
    // (asset.uri -> Blob, nothing to do with this app's own API).
    const installedFetch = global.fetch;
    global.fetch = jest.fn(async (input: unknown, init?: RequestInit) => {
      const url = String(input);

      if (url === 'file:///mock-logo.jpg') {
        return { blob: async () => new Blob(['fake-image-bytes'], { type: 'image/jpeg' }) } as unknown as Response;
      }

      return installedFetch(input as RequestInfo, init);
    }) as unknown as typeof fetch;

    const queryClient = new QueryClient();
    render(
      <QueryClientProvider client={queryClient}>
        <AuthProvider>
          <OrganizationProvider>
            <OrganizationEditScreen />
          </OrganizationProvider>
        </AuthProvider>
      </QueryClientProvider>
    );

    await waitFor(() => expect(screen.getByTestId('organization-edit-logo-empty')).toBeVisible());
    expect(screen.queryByTestId('organization-edit-logo-preview')).toBeNull();

    const fetchCallsBeforeUpload = (global.fetch as jest.Mock).mock.calls.length;

    fireEvent.press(screen.getByTestId('organization-edit-logo-change'));

    await waitFor(() => expect(screen.getByTestId('organization-edit-logo-preview')).toBeVisible());
    expect(screen.getByTestId('organization-edit-logo-preview').props.source).toMatchObject({ uri: NEW_LOGO_URL });
    // The whole logo is shown at its own aspect ratio - never cropped by cover.
    expect(screen.getByTestId('organization-edit-logo-preview').props.resizeMode).toBe('contain');

    const postCall = (global.fetch as jest.Mock).mock.calls.find(
      ([url, init]: [string, RequestInit]) => String(url).endsWith('/organizations/org-1/logo') && init?.method === 'POST'
    );
    expect(postCall).toBeDefined();

    // The preview update above is driven entirely by the mutation's own
    // onSuccess synchronously patching the `organizations` cache - no
    // additional GET /organizations re-fetch should have been needed to
    // reach it.
    const callsSinceUpload = (global.fetch as jest.Mock).mock.calls.slice(fetchCallsBeforeUpload);
    expect(callsSinceUpload.length).toBeGreaterThan(0);
    const getOrganizationsCallsSinceUpload = callsSinceUpload.filter(
      ([url, init]: [string, RequestInit?]) => String(url).endsWith('/organizations') && (init?.method ?? 'GET') === 'GET'
    ).length;
    expect(getOrganizationsCallsSinceUpload).toBe(0);
  });
});
