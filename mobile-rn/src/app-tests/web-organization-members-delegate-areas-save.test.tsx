import { fireEvent, screen, waitFor } from '@testing-library/react-native';

import { orgOne } from './__fixtures__/homeFixtures';
import { AREAS, fetchCalls, renderMembersAs } from './__fixtures__/membersScreenHarness';

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
 * 代理人設定 (docs/03-PublicPageURLAndPublicationSchedule.md「代理人設定」):
 * the Owner sees one checkbox per 管理領域 on each delegate's row, the
 * Owner's own row fixed at every area, and nothing on a plain member's row.
 * Checking a second area and pressing 更新 sends the delegate's whole set
 * to the API, then shows 保存しました with the saved state.
 */
describe('Web メンバー管理: 代理人の管理領域を設定する (Owner)', () => {
  it('shows the saved areas, saves a second one through the API and keeps it checked', async () => {
    let saved = ['MEMBERS'];
    renderMembersAs(orgOne, [
      { test: (u) => u.endsWith('/organizations/org-1/delegates'), status: 200, get body() { return [{ person_id: 'person-3', areas: saved }]; } },
      {
        test: (u) => u.endsWith('/organizations/org-1/delegates/person-3/areas'),
        status: 200,
        get body() {
          saved = ['MEMBERS', 'PRODUCTIONS'];
          return { person_id: 'person-3', areas: saved };
        },
      },
    ]);

    const checkbox = (area: string, person: string) => screen.getByTestId(`organization-member-area-${area}-${person}`);

    await waitFor(() => expect(checkbox('MEMBERS', 'person-3').props.accessibilityState).toMatchObject({ checked: true, disabled: false }));
    expect(checkbox('PRODUCTIONS', 'person-3').props.accessibilityState).toMatchObject({ checked: false });
    expect(checkbox('ACCOUNTING', 'person-3').props.accessibilityState).toMatchObject({ checked: false });
    // The Owner holds every area, fixed; a plain member has no area checkbox at all.
    AREAS.forEach((area) => {
      expect(checkbox(area, 'person-1').props.accessibilityState).toEqual({ checked: true, disabled: true });
      expect(screen.queryByTestId(`organization-member-area-${area}-person-2`)).toBeNull();
    });
    expect(screen.getByText('公演管理')).toBeVisible();
    expect(screen.getByText('会計管理')).toBeVisible();

    fireEvent.press(checkbox('PRODUCTIONS', 'person-3'));
    await waitFor(() => expect(checkbox('PRODUCTIONS', 'person-3').props.accessibilityState).toMatchObject({ checked: true }));
    fireEvent.press(screen.getByTestId('organization-members-save'));

    await waitFor(() => expect(screen.getByTestId('organization-members-saved')).toBeVisible());
    const puts = fetchCalls().filter(([url, method]) => method === 'PUT' && url.endsWith('/organizations/org-1/delegates/person-3/areas'));
    expect(puts).toHaveLength(1);
    expect(puts[0][2]).toEqual({ areas: ['MEMBERS', 'PRODUCTIONS'] });
    await waitFor(() => expect(checkbox('PRODUCTIONS', 'person-3').props.accessibilityState).toMatchObject({ checked: true }));
    expect(screen.queryByTestId('organization-members-save-error')).toBeNull();
  });
});
