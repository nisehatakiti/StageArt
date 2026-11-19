import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react-native';
import * as ImagePicker from 'expo-image-picker';

import { AuthProvider } from '@/auth/AuthContext';
import { OrganizationProvider } from '@/features/organization/OrganizationContext';
import type { Organization } from '@/types/api';

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
  useRouter: () => ({ push: jest.fn(), replace: jest.fn(), setParams: jest.fn() }),
  useLocalSearchParams: () => ({ id: 'org-1' }),
}));

jest.mock('expo-image-picker', () => ({
  requestMediaLibraryPermissionsAsync: jest.fn(async () => ({ granted: true })),
  launchImageLibraryAsync: jest.fn(async () => ({ canceled: true, assets: [] })),
}));

const LOGO = 'https://dev-api.stageart.top/wp-content/uploads/2026/10/current.png';

function renderAs(organizations: Organization[]) {
  mockFetchRoutes([
    { test: (u) => u.endsWith('/organizations'), status: 200, body: organizations },
    { test: (u) => u.endsWith('/organizations/org-1/sns'), status: 200, body: [] },
    { test: (u) => u.endsWith('/organizations/org-1/external-links'), status: 200, body: [] },
  ]);

  render(
    <QueryClientProvider client={new QueryClient()}>
      <AuthProvider>
        <OrganizationProvider>
          <OrganizationEditScreen />
        </OrganizationProvider>
      </AuthProvider>
    </QueryClientProvider>
  );
}

function expectNoLogoUpload() {
  // Not a disabled control: the logo card and its file picking are not there at all.
  expect(screen.queryByTestId('organization-edit-logo-change')).toBeNull();
  expect(screen.queryByTestId('organization-edit-logo-delete')).toBeNull();
  expect(screen.queryByTestId('organization-edit-logo-empty')).toBeNull();
  expect(screen.queryByText('団体ロゴ')).toBeNull();
  expect(ImagePicker.launchImageLibraryAsync).not.toHaveBeenCalled();
  const logoCalls = (global.fetch as jest.Mock).mock.calls.filter(([url]: [string]) => String(url).endsWith('/logo'));
  expect(logoCalls).toHaveLength(0);
}

/**
 * The logo upload is offered only to the people the API lets change the
 * logo (UpdateOrganizationLogoUseCase: OWNER / ORGANIZATION_MANAGER, the
 * role the API reports as current_person_role). Everyone else does not
 * see it at all; the API refuses them as well (403, checked before the
 * file is accepted).
 */
describe('Web 団体情報編集: ロゴアップロードは権限のある人だけに表示', () => {
  beforeEach(() => jest.clearAllMocks());

  it('shows the logo upload to the owner', async () => {
    renderAs([{ ...orgOne, logo_url: LOGO, current_person_role: 'OWNER' }]);

    await waitFor(() => expect(screen.getByTestId('organization-edit-logo-change')).toBeVisible());
    expect(screen.getByTestId('organization-edit-logo-delete')).toBeVisible();
  });

  it('shows the logo upload to a delegate (代理人)', async () => {
    renderAs([{ ...orgOne, logo_url: LOGO, current_person_role: 'ORGANIZATION_MANAGER' }]);

    await waitFor(() => expect(screen.getByTestId('organization-edit-logo-change')).toBeVisible());
    expect(screen.getByTestId('organization-edit-logo-delete')).toBeVisible();
  });

  it('does not show it to a plain member', async () => {
    renderAs([{ ...orgOne, logo_url: LOGO, current_person_role: 'MEMBER' }]);

    await waitFor(() => expect(screen.getByTestId('organization-edit-forbidden')).toBeVisible());
    expectNoLogoUpload();
  });

  it('does not show it to someone unrelated to the organization', async () => {
    renderAs([]);

    await waitFor(() => expect(screen.getByTestId('organization-edit-not-found')).toBeVisible());
    expectNoLogoUpload();
  });
});
