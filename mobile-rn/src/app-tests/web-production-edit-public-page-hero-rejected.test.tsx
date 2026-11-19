import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

import { AuthProvider } from '@/auth/AuthContext';
import { OrganizationProvider } from '@/features/organization/OrganizationContext';

import { mockFetchRoutes, orgOne, productionOne, projectOne } from './__fixtures__/homeFixtures';
import { mockPublicPageEndpoint, publicPageSettingsFixture } from './__fixtures__/publicPageFixtures';
import ProductionEditScreen from '../app/(app)/productions/[id]/edit';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

jest.mock('expo-router', () => ({
  useRouter: () => ({ push: jest.fn(), replace: jest.fn(), setParams: jest.fn() }),
  useLocalSearchParams: () => ({ id: 'prod-1', tab: 'public-page' }),
}));

jest.mock('expo-image-picker', () => ({
  requestMediaLibraryPermissionsAsync: jest.fn(async () => ({ granted: true })),
  launchImageLibraryAsync: jest.fn(async () => ({
    canceled: false,
    assets: [{ uri: 'file:///mock-hero.jpg', fileName: 'mock-hero.jpg' }],
  })),
}));

function render422(code: string, message: string) {
  const withEndpoint = global.fetch;
  global.fetch = jest.fn(async (input: unknown, init?: RequestInit) => {
    if (String(input).endsWith('/productions/prod-1/public-page/hero-image')) {
      const body = { code, message, data: { status: 422 } };
      return { ok: false, status: 422, text: async () => JSON.stringify(body), json: async () => body } as unknown as Response;
    }
    return withEndpoint(input as RequestInfo, init);
  }) as unknown as typeof fetch;

  render(
    <QueryClientProvider client={new QueryClient()}>
      <AuthProvider>
        <OrganizationProvider>
          <ProductionEditScreen />
        </OrganizationProvider>
      </AuthProvider>
    </QueryClientProvider>
  );
}

async function pickHeroImage() {
  await waitFor(() => expect(screen.getByTestId('production-public-page-settings')).toBeVisible());
  await fireEvent(screen.getByTestId('production-hero-enabled'), 'valueChange', true);
  await waitFor(() => expect(screen.getByTestId('production-hero-options')).toBeVisible());
  await fireEvent.press(screen.getByTestId('production-hero-type-custom'));
  await fireEvent.press(screen.getByTestId('production-hero-custom-upload'));
}

/**
 * The server rejects an own hero image with a reason (over 10MB, broken,
 * animation over 5MB, cannot fit in 5MB ...): that reason is what the
 * manager sees, not a bare "読み込みに失敗しました。(422)".
 */
describe('Web 公開ページ設定: 独自画像のアップロードが拒否されたとき', () => {
  beforeEach(() => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [{ ...orgOne, slug: 'kujira' }] },
      { test: (u) => u.endsWith('/projects'), status: 200, body: [projectOne] },
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne },
    ]);
    mockPublicPageEndpoint('/productions/prod-1/public-page', publicPageSettingsFixture('production'));
  });

  it("shows the server's reason for a rejected image and keeps no image", async () => {
    render422('stageart_public_page_hero_image_rejected', '画像のファイルサイズは10MB以下にしてください。');
    await pickHeroImage();

    await waitFor(() => expect(screen.getByTestId('production-hero-custom-error')).toHaveTextContent('画像のファイルサイズは10MB以下にしてください。'));
    expect(screen.getByTestId('production-hero-custom-empty')).toBeVisible();
    expect(screen.queryByTestId('production-hero-custom-preview')).toBeNull();
  });

  it('keeps the generic message for other 422 errors', async () => {
    render422('stageart_public_page_hero_image_invalid', 'A "file" upload is required.');
    await pickHeroImage();

    await waitFor(() => expect(screen.getByTestId('production-hero-custom-error')).toHaveTextContent('読み込みに失敗しました。(422)'));
  });
});
