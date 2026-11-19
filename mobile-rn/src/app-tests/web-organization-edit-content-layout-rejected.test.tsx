import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

import { AuthProvider } from '@/auth/AuthContext';
import { OrganizationProvider } from '@/features/organization/OrganizationContext';

import { contentAdminFixture, mockContentEndpoints } from './__fixtures__/contentFixtures';
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
  useLocalSearchParams: () => ({ id: 'org-1', tab: 'content' }),
}));

/** A layout the API rejects (422) is not reported as saved, and the editor keeps what was entered. */
describe('Web 団体情報: コンテンツ配置の保存エラー', () => {
  it('keeps the edits and shows that saving failed', async () => {
    mockFetchRoutes([{ test: (u) => u.endsWith('/organizations'), status: 200, body: [orgOne] }]);
    mockContentEndpoints('org-1', contentAdminFixture(), { layoutError: { code: 'stageart_public_page_content_invalid', message: 'リンクには http:// または https:// で始まるURLを指定してください。' } });

    render(
      <QueryClientProvider client={new QueryClient()}>
        <AuthProvider>
          <OrganizationProvider>
            <OrganizationEditScreen />
          </OrganizationProvider>
        </AuthProvider>
      </QueryClientProvider>
    );

    await waitFor(() => expect(screen.getByTestId('organization-content-panel')).toBeVisible());
    await fireEvent.press(screen.getByTestId('organization-content-block-0-section-0-slot-0-kind-url'));
    await fireEvent.changeText(screen.getByTestId('organization-content-block-0-section-0-slot-0-url'), 'ftp://example.com');
    await fireEvent.press(screen.getByTestId('organization-content-layout-submit'));

    await waitFor(() => expect(screen.getByTestId('organization-content-layout-error')).toHaveTextContent('リンクには http:// または https:// で始まるURLを指定してください。'));
    expect(screen.queryByTestId('organization-content-layout-saved')).toBeNull();
    expect(screen.getByTestId('organization-content-block-0-section-0-slot-0-url').props.value).toBe('ftp://example.com');
    expect(screen.getByTestId('organization-content-layout-unsaved')).toBeVisible();
  });
});
