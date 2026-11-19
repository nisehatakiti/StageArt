import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

import { AuthProvider } from '@/auth/AuthContext';
import { OrganizationProvider } from '@/features/organization/OrganizationContext';

import { contentAdminFixture, contentFixture, mockContentEndpoints } from './__fixtures__/contentFixtures';
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

/**
 * コンテンツ tab: a 自由コンテンツ is hidden (draft) and shown again with
 * one switch, edited, and deleted only after a confirmation; a saved
 * layout is loaded as stored (blocks / sections / slots in order).
 */
describe('Web 団体情報: 自由コンテンツの表示切替・編集・削除', () => {
  it('toggles, edits and deletes a free content, and loads the saved layout', async () => {
    mockFetchRoutes([{ test: (u) => u.endsWith('/organizations'), status: 200, body: [orgOne] }]);
    const endpoint = mockContentEndpoints(
      'org-1',
      contentAdminFixture({
        contents: [contentFixture()],
        layout: [
          { block_id: 'b1', sections: [{ section_id: 's1', heading: '最初', columns: 1, layout: 'vertical', slots: [{ type: 'heading', ref: '見出し', indent: 0 }] }] },
          { block_id: 'b2', sections: [{ section_id: 's2', heading: '次', columns: 1, layout: 'vertical', slots: [{ type: 'production', ref: 'prod-1', indent: 2 }] }] },
        ],
      })
    );

    render(
      <QueryClientProvider client={new QueryClient()}>
        <AuthProvider>
          <OrganizationProvider>
            <OrganizationEditScreen />
          </OrganizationProvider>
        </AuthProvider>
      </QueryClientProvider>
    );

    // ?tab=content opens the tab directly.
    await waitFor(() => expect(screen.getByTestId('organization-content-panel')).toBeVisible());
    expect(screen.queryByTestId('organization-content-layout-unsaved')).toBeNull();
    expect(screen.getByTestId('organization-content-block-0-section-0-heading').props.value).toBe('最初');
    expect(screen.getByTestId('organization-content-block-0-section-0-slot-0-heading').props.value).toBe('見出し');
    expect(screen.getByTestId('organization-content-block-1-section-0-slot-0-content-production:prod-1').props.accessibilityState).toMatchObject({ selected: true });
    expect(screen.getByTestId('organization-content-block-1-section-0-slot-0-indent-2').props.accessibilityState).toMatchObject({ selected: true });

    // Blocks move: the second block goes first.
    await fireEvent.press(screen.getByTestId('organization-content-block-1-up'));
    expect(screen.getByTestId('organization-content-block-0-section-0-heading').props.value).toBe('次');

    // Hide -> draft (still listed, marked hidden).
    await fireEvent.press(screen.getByTestId('organization-free-content-content-1-toggle'));
    await waitFor(() => expect(screen.getByTestId('organization-free-content-content-1-status')).toHaveTextContent('下書き（非表示）'));
    expect(endpoint.requests[0]).toEqual({ method: 'PUT', path: '/contents/content-1', body: { title: 'お知らせ', body: '本文', status: 'draft' } });

    // Edit.
    await fireEvent.press(screen.getByTestId('organization-free-content-content-1-edit'));
    await fireEvent.changeText(screen.getByTestId('organization-free-content-title'), 'お知らせ（更新）');
    await fireEvent(screen.getByTestId('organization-free-content-publish'), 'valueChange', true);
    await fireEvent.press(screen.getByTestId('organization-free-content-submit'));
    await waitFor(() => expect(screen.getByTestId('organization-free-contents-message')).toHaveTextContent('自由コンテンツを更新しました。'));
    expect(endpoint.requests[1]).toEqual({ method: 'PUT', path: '/contents/content-1', body: { title: 'お知らせ（更新）', body: '本文', status: 'publish' } });

    // Delete needs a second press.
    await fireEvent.press(screen.getByTestId('organization-free-content-content-1-delete'));
    expect(endpoint.requests).toHaveLength(2);
    await fireEvent.press(screen.getByTestId('organization-free-content-content-1-delete-confirm'));
    await waitFor(() => expect(screen.getByTestId('organization-free-contents-empty')).toBeVisible());
    expect(endpoint.requests[2]).toEqual({ method: 'DELETE', path: '/contents/content-1', body: undefined });
  });
});
