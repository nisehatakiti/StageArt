import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

import { AuthProvider } from '@/auth/AuthContext';
import { OrganizationProvider } from '@/features/organization/OrganizationContext';

import { mockFetchRoutes, orgOne } from './__fixtures__/homeFixtures';
import { mockPublicPageEndpoint, publicPageSettingsFixture } from './__fixtures__/publicPageFixtures';
import OrganizationEditScreen from '../app/(app)/organizations/[id]/edit';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

const mockSetParams = jest.fn();
jest.mock('expo-router', () => ({
  useRouter: () => ({ push: jest.fn(), replace: jest.fn(), setParams: mockSetParams }),
  useLocalSearchParams: () => ({ id: 'org-1' }),
}));

/**
 * 公開ページ設定 tab of the organization edit screen: the tab exists next
 * to 基本情報, loads the saved settings, and saves a theme preset + custom
 * colors/fonts + hero preset in one PUT to this organization's own
 * endpoint. (One it() per file - see web-production-edit-save.test.tsx.)
 */
describe('Web 団体情報: 公開ページ設定タブ', () => {
  it('switches to the tab, edits theme and hero, and saves them for this organization', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [orgOne] },
      { test: (u) => u.endsWith('/organizations/org-1/sns'), status: 200, body: [] },
      { test: (u) => u.endsWith('/organizations/org-1/external-links'), status: 200, body: [] },
    ]);
    const endpoint = mockPublicPageEndpoint('/organizations/org-1/public-page', publicPageSettingsFixture('organization'));

    render(
      <QueryClientProvider client={new QueryClient()}>
        <AuthProvider>
          <OrganizationProvider>
            <OrganizationEditScreen />
          </OrganizationProvider>
        </AuthProvider>
      </QueryClientProvider>
    );

    // 基本情報 is the default tab and still shows the existing form.
    await waitFor(() => expect(screen.getByTestId('organization-edit-name')).toBeVisible());
    expect(screen.getByTestId('organization-edit-tabs-basic')).toHaveTextContent('基本情報');
    expect(screen.getByTestId('organization-edit-tabs-public-page')).toHaveTextContent('公開ページ設定');
    expect(screen.queryByTestId('organization-public-page-settings')).toBeNull();

    await fireEvent.press(screen.getByTestId('organization-edit-tabs-public-page'));
    expect(mockSetParams).toHaveBeenCalledWith({ tab: 'public-page' });

    await waitFor(() => expect(screen.getByTestId('organization-public-page-settings')).toBeVisible());
    expect(screen.queryByTestId('organization-edit-name')).toBeNull();
    // Preset names come from the API response.
    expect(screen.getByTestId('organization-theme-preset-light_standard')).toHaveTextContent(/ライト・スタンダード/);
    expect(screen.getByTestId('organization-theme-preset-light_standard').props.accessibilityState).toMatchObject({ selected: true });
    expect(screen.queryByTestId('organization-theme-custom-fields')).toBeNull();

    await fireEvent.press(screen.getByTestId('organization-theme-preset-dark_theater'));
    await fireEvent(screen.getByTestId('organization-theme-custom-mode'), 'valueChange', true);
    await waitFor(() => expect(screen.getByTestId('organization-theme-custom-fields')).toBeVisible());
    await fireEvent.changeText(screen.getByTestId('organization-theme-color-accent-hex'), '#ff8800');
    await fireEvent.press(screen.getByTestId('organization-theme-heading_font-serif'));

    await fireEvent(screen.getByTestId('organization-hero-enabled'), 'valueChange', true);
    await waitFor(() => expect(screen.getByTestId('organization-hero-options')).toBeVisible());
    await fireEvent.press(screen.getByTestId('organization-hero-preset-confetti'));
    await fireEvent.changeText(screen.getByTestId('organization-hero-title'), '劇団の新作');

    await fireEvent.press(screen.getByTestId('organization-public-page-submit'));

    await waitFor(() => expect(screen.getByTestId('organization-public-page-saved')).toHaveTextContent('公開ページ設定を保存しました。'));
    expect(endpoint.puts).toHaveLength(1);
    expect(endpoint.puts[0]).toEqual({
      theme: {
        mode: 'custom',
        preset: 'dark_theater',
        custom: { bg: '#f7f5f0', surface: '#ffffff', text: '#242424', primary: '#333333', accent: '#ff8800', border: '#ddd7cc', heading_font: 'serif', body_font: 'system' },
      },
      hero: { enabled: true, type: 'preset', preset: 'confetti', title: '劇団の新作', description: '' },
    });
  });
});
