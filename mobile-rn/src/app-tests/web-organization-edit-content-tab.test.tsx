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

const mockSetParams = jest.fn();
jest.mock('expo-router', () => ({
  useRouter: () => ({ push: jest.fn(), replace: jest.fn(), setParams: mockSetParams }),
  useLocalSearchParams: () => ({ id: 'org-1' }),
}));

/**
 * コンテンツ tab of the organization edit screen (StageArtCore's homepage
 * コンテンツブロック + 自由コンテンツ): the third tab, a 自由コンテンツ is
 * added, and a two-column section placing the 公演一覧 and that content is
 * saved, in order, to this organization's own layout endpoint.
 */
describe('Web 団体情報: コンテンツタブ', () => {
  it('adds a free content and saves a layout that places it', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [orgOne] },
      { test: (u) => u.endsWith('/organizations/org-1/sns'), status: 200, body: [] },
      { test: (u) => u.endsWith('/organizations/org-1/external-links'), status: 200, body: [] },
    ]);
    const endpoint = mockContentEndpoints('org-1', contentAdminFixture());

    render(
      <QueryClientProvider client={new QueryClient()}>
        <AuthProvider>
          <OrganizationProvider>
            <OrganizationEditScreen />
          </OrganizationProvider>
        </AuthProvider>
      </QueryClientProvider>
    );

    await waitFor(() => expect(screen.getByTestId('organization-edit-name')).toBeVisible());
    expect(screen.getByTestId('organization-edit-tabs-content')).toHaveTextContent('コンテンツ');

    await fireEvent.press(screen.getByTestId('organization-edit-tabs-content'));
    expect(mockSetParams).toHaveBeenCalledWith({ tab: 'content' });
    await waitFor(() => expect(screen.getByTestId('organization-content-panel')).toBeVisible());
    expect(screen.queryByTestId('organization-edit-name')).toBeNull();

    // Never saved: the page keeps its fixed order; the editor starts from Core's default.
    expect(screen.getByTestId('organization-content-layout-unsaved')).toBeVisible();
    expect(screen.getByTestId('organization-content-block-0-section-0-heading').props.value).toBe('公演');
    expect(screen.getByTestId('organization-content-block-0-section-0-slot-0-content-system:productions').props.accessibilityState).toMatchObject({ selected: true });

    // 自由コンテンツ: add one.
    await fireEvent.press(screen.getByTestId('organization-free-content-add'));
    await fireEvent.changeText(screen.getByTestId('organization-free-content-title'), '劇団からのお知らせ');
    await fireEvent.changeText(screen.getByTestId('organization-free-content-body'), '新メンバーを募集しています。\n詳しくはお問い合わせください。');
    await fireEvent.press(screen.getByTestId('organization-free-content-submit'));
    await waitFor(() => expect(screen.getByTestId('organization-free-contents-message')).toHaveTextContent('自由コンテンツを追加しました。'));
    expect(endpoint.requests[0]).toEqual({
      method: 'POST',
      path: '/contents',
      body: { title: '劇団からのお知らせ', body: '新メンバーを募集しています。\n詳しくはお問い合わせください。', status: 'publish' },
    });
    await waitFor(() => expect(screen.getByTestId('organization-free-content-new-content-1-status')).toHaveTextContent('公開'));

    // Layout: 2 columns, horizontal, the new content in column 2 with one indent step.
    await fireEvent.press(screen.getByTestId('organization-content-block-0-section-0-columns-2'));
    await fireEvent.press(screen.getByTestId('organization-content-block-0-section-0-layout-horizontal'));
    await fireEvent.press(screen.getByTestId('organization-content-block-0-section-0-slot-1-kind-content'));
    await fireEvent.press(screen.getByTestId('organization-content-block-0-section-0-slot-1-content-free_content:new-content-1'));
    await fireEvent.press(screen.getByTestId('organization-content-block-0-section-0-slot-1-indent-1'));
    await fireEvent.changeText(screen.getByTestId('organization-content-block-0-section-0-heading'), '公演とお知らせ');

    await fireEvent.press(screen.getByTestId('organization-content-layout-submit'));
    await waitFor(() => expect(screen.getByTestId('organization-content-layout-saved')).toHaveTextContent('コンテンツ配置を保存しました。'));

    const put = endpoint.requests.find((r) => r.path === '/content-layout');
    expect(put?.method).toBe('PUT');
    const blocks = (put?.body as { blocks: { sections: unknown[] }[] }).blocks;
    expect(blocks).toHaveLength(1);
    expect(blocks[0].sections).toEqual([
      expect.objectContaining({
        heading: '公演とお知らせ',
        columns: 2,
        layout: 'horizontal',
        slots: [
          { type: 'system', ref: 'productions', indent: 0 },
          { type: 'free_content', ref: 'new-content-1', indent: 1 },
        ],
      }),
    ]);
    expect(screen.queryByTestId('organization-content-layout-unsaved')).toBeNull();
  });
});
