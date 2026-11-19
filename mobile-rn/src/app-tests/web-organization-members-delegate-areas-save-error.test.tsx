import { fireEvent, screen, waitFor } from '@testing-library/react-native';

import { orgOne } from './__fixtures__/homeFixtures';
import { renderMembersAs } from './__fixtures__/membersScreenHarness';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

jest.mock('expo-router', () => ({
  useRouter: () => ({ push: jest.fn(), replace: jest.fn() }),
  useLocalSearchParams: () => ({ id: 'org-1' }),
}));

/**
 * When the API refuses the change, the screen says so and never shows
 * 保存しました; the unsaved choice stays on screen (not silently reverted
 * as if nothing happened, nor presented as saved).
 */
describe('Web メンバー管理: 代理人の管理領域の保存に失敗したとき', () => {
  it('shows the error and no success message', async () => {
    renderMembersAs(orgOne, [
      { test: (u) => u.endsWith('/organizations/org-1/delegates'), status: 200, body: [{ person_id: 'person-3', areas: [] }] },
      {
        test: (u) => u.endsWith('/organizations/org-1/delegates/person-3/areas'),
        status: 403,
        body: { code: 'stageart_organization_access_denied', message: 'Only the Organization Owner can change delegate areas.', data: { status: 403 } },
      },
    ]);

    await waitFor(() => expect(screen.getByTestId('organization-member-area-ACCOUNTING-person-3')).toBeVisible());
    fireEvent.press(screen.getByTestId('organization-member-area-ACCOUNTING-person-3'));
    await waitFor(() => expect(screen.getByTestId('organization-member-area-ACCOUNTING-person-3').props.accessibilityState).toMatchObject({ checked: true }));
    fireEvent.press(screen.getByTestId('organization-members-save'));

    await waitFor(() => expect(screen.getByTestId('organization-members-save-error')).toBeVisible());
    expect(screen.queryByTestId('organization-members-saved')).toBeNull();
    expect(screen.getByTestId('organization-member-area-ACCOUNTING-person-3').props.accessibilityState).toMatchObject({ checked: true });
  });
});
