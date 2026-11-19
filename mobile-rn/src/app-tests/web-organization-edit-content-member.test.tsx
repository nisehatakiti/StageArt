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

/**
 * Members are not typed into the コンテンツ tab: they are picked from the
 * members メンバー管理 has (the API's choices.members, name only) and the
 * layout keeps only the Person id. There is no form to enter a member.
 */
describe('Web 団体情報: コンテンツタブのメンバー', () => {
  it('places a member from メンバー管理 by reference', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [orgOne] },
      { test: (u) => u.endsWith('/organizations/org-1/sns'), status: 200, body: [] },
      { test: (u) => u.endsWith('/organizations/org-1/external-links'), status: 200, body: [] },
    ]);
    const base = contentAdminFixture();
    const endpoint = mockContentEndpoints(
      'org-1',
      contentAdminFixture({
        choices: { ...base.choices, members: [{ person_id: 'person-yamada', name: '山田 太郎' }, { person_id: 'person-sato', name: '佐藤 花子' }] },
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

    await waitFor(() => expect(screen.getByTestId('organization-content-panel')).toBeVisible());

    const slot = 'organization-content-block-0-section-0-slot-0';
    expect(screen.getByTestId(`${slot}-member-note`)).toHaveTextContent(/メンバー管理/);
    expect(screen.getByTestId(`${slot}-content-member:person-yamada`)).toHaveTextContent('山田 太郎');
    expect(screen.getByTestId(`${slot}-content-member:person-sato`)).toHaveTextContent('佐藤 花子');
    // No place to enter or edit a member here.
    expect(screen.queryByPlaceholderText(/氏名|名前/)).toBeNull();

    await fireEvent.press(screen.getByTestId(`${slot}-content-member:person-sato`));
    await fireEvent.press(screen.getByTestId('organization-content-layout-submit'));
    await waitFor(() => expect(screen.getByTestId('organization-content-layout-saved')).toBeVisible());

    const put = endpoint.requests.find((r) => r.path === '/content-layout');
    const slots = (put?.body as { blocks: { sections: { slots: unknown[] }[] }[] }).blocks[0].sections[0].slots;
    expect(slots).toEqual([{ type: 'member', ref: 'person-sato', indent: 0 }]);
    // Only the content endpoints were written to: nothing about the member itself.
    expect(endpoint.requests.map((r) => `${r.method} ${r.path}`)).toEqual(['PUT /content-layout']);
  });
});
