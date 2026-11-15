import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty, orgTwo } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/** A plain Member navigating directly to the edit URL (not just clicking
 * a disabled card) must still be refused - the server enforces this
 * (UpdateOrganizationUseCase: "Only an Organization Owner can update
 * this Organization"), and this screen mirrors that decision instead of
 * showing an edit form no Member could ever successfully submit. */
describe('Web 団体情報編集: Member is refused', () => {
  it('shows a forbidden message instead of the edit form for a plain Member', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [orgTwo] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/organizations/org-2/edit' });

    await waitFor(() => expect(screen.getByTestId('organization-edit-forbidden')).toBeVisible());
    expect(screen.queryByTestId('organization-edit-name')).toBeNull();
  });
});
