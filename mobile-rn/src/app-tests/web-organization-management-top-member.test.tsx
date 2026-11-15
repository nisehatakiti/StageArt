import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty, orgTwo } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * StageArt Web版 団体管理 Phase: this Phase's own explicit instruction
 * ("Frontendだけで「Ownerです」と決め打ちする実装は禁止") applied to a plain
 * MEMBER (orgTwo's fixed `current_person_role: 'MEMBER'`) viewing the
 * management top screen.
 *
 * StageArt Organization Context Menu整理: this screen previously rendered
 * its own MenuCard grid with Owner-only gating (参加申請/招待 hidden,
 * 団体情報 disabled) duplicating the Organization Context left sidebar's
 * own gating. That grid is now removed - the Owner/Member gating
 * guarantee itself is covered at its actual source, useNavMenu.ts (see
 * useNavMenu.test.tsx's "hides Owner-only Organization Context items and
 * disables 団体情報 for a MEMBER"). This test now only confirms the
 * management top screen itself still renders correctly for a non-Owner.
 */
describe('Web 団体管理トップ: Member (not Owner)', () => {
  it('renders the Organization name for a plain Member', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [orgTwo] },
      { test: (u) => u.endsWith('/projects'), status: 200, body: [] },
      { test: (u) => u.endsWith('/productions'), status: 200, body: [] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/organizations/org-2' });

    await waitFor(() => expect(screen.getByTestId('organization-management-name')).toBeVisible());
    expect(screen.getByTestId('organization-management-name').props.children).toBe(orgTwo.name);
  });
});
