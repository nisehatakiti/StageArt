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

// Opened directly on the tab (?tab=public-page), as after a reload.
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

/**
 * 公開ページ設定 tab of the production edit screen: the saved custom theme
 * is loaded back into the form (reload), the production-only presets are
 * offered, an own hero image is uploaded to this production's endpoint and
 * selected, and the save goes to /productions/{id}/public-page only.
 */
describe('Web 公演情報編集: 公開ページ設定タブ', () => {
  it('reloads the saved custom theme, uploads an own hero image and saves for this production', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [{ ...orgOne, slug: 'kujira' }] },
      { test: (u) => u.endsWith('/projects'), status: 200, body: [projectOne] },
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne },
    ]);
    const saved = publicPageSettingsFixture('production', {
      theme: {
        mode: 'custom',
        preset: 'dark_drama',
        custom: { bg: '#101010', surface: '#202020', text: '#fafafa', primary: '#ff8800', accent: '#00aaff', border: '#333333', heading_font: 'serif', body_font: 'sans' },
      },
    });
    const endpoint = mockPublicPageEndpoint('/productions/prod-1/public-page', saved);

    render(
      <QueryClientProvider client={new QueryClient()}>
        <AuthProvider>
          <OrganizationProvider>
            <ProductionEditScreen />
          </OrganizationProvider>
        </AuthProvider>
      </QueryClientProvider>
    );

    await waitFor(() => expect(screen.getByTestId('production-public-page-settings')).toBeVisible());
    expect(screen.getByTestId('production-edit-tabs-information')).toHaveTextContent('公演情報');
    // The 公演情報 form is a separate tab.
    expect(screen.queryByTestId('production-edit-name')).toBeNull();

    // Saved settings are loaded back.
    expect(screen.getByTestId('production-theme-preset-dark_drama')).toHaveTextContent(/ダーク・ドラマ/);
    expect(screen.getByTestId('production-theme-preset-dark_drama').props.accessibilityState).toMatchObject({ selected: true });
    expect(screen.getByTestId('production-theme-custom-mode').props.value).toBe(true);
    expect(screen.getByTestId('production-theme-color-primary-hex').props.value).toBe('#ff8800');
    expect(screen.getByTestId('production-theme-body_font-sans').props.accessibilityState).toMatchObject({ selected: true });

    // An invalid color blocks saving.
    await fireEvent.changeText(screen.getByTestId('production-theme-color-bg-hex'), 'red');
    await waitFor(() => expect(screen.getByTestId('production-public-page-submit').props.accessibilityState).toMatchObject({ disabled: true }));
    await fireEvent.changeText(screen.getByTestId('production-theme-color-bg-hex'), '#000000');

    await fireEvent(screen.getByTestId('production-hero-enabled'), 'valueChange', true);
    await waitFor(() => expect(screen.getByTestId('production-hero-options')).toBeVisible());
    await fireEvent.press(screen.getByTestId('production-hero-type-custom'));
    expect(screen.getByTestId('production-hero-custom-empty')).toBeVisible();

    await fireEvent.press(screen.getByTestId('production-hero-custom-upload'));
    await waitFor(() => expect(screen.getByTestId('production-hero-custom-preview')).toBeVisible());
    expect(screen.getByTestId('production-hero-custom-preview').props.source).toMatchObject({
      uri: 'https://api.test/wp-content/uploads/2026/10/own-hero.jpg',
    });
    const uploadCall = (global.fetch as jest.Mock).mock.calls.find(
      ([url, init]: [string, RequestInit]) => String(url).endsWith('/productions/prod-1/public-page/hero-image') && init?.method === 'POST'
    );
    expect(uploadCall).toBeDefined();

    await fireEvent.press(screen.getByTestId('production-public-page-submit'));

    await waitFor(() => expect(screen.getByTestId('production-public-page-saved')).toBeVisible());
    expect(endpoint.puts).toEqual([
      {
        theme: { ...saved.theme, custom: { ...saved.theme.custom, bg: '#000000' } },
        hero: { enabled: true, type: 'custom', preset: null, title: '', description: '' },
      },
    ]);
    // Nothing was sent to the organization's settings.
    const organizationCalls = (global.fetch as jest.Mock).mock.calls.filter(([url]: [string]) => String(url).includes('/organizations/org-1/public-page'));
    expect(organizationCalls).toHaveLength(0);
  });
});
