import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { currentPerson, mockFetchRoutes, participants, productionOne, staffItem } from './__fixtures__/scheduleFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * docs/04-CommonNavigationDesign.md §20.3/§20.6 and PR #7 R8 (2-5): the
 * 小屋入り～本番 → タイムテーブル screen offers 「＋ タイムテーブルを作成」 and
 * 「🖨 タイムテーブルを印刷」. 作成 opens the independent timetable editor -
 * not the 予定 (稽古) list: the 稽古 detail screen has no timetable
 * controls (2c0c20b, 22e0285).
 */
describe('Schedule: タイムテーブルを作成 entry point', () => {
  it('shows 作成 and 印刷 to a manager, and 作成 opens the timetable editor', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/me'), status: 200, body: currentPerson },
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne },
      { test: (u) => u.includes('/productions/prod-1/participants'), status: 200, body: participants },
      { test: (u) => u.includes('/productions/prod-1/timetable-items'), status: 200, body: [staffItem] },
      { test: (u) => u.includes('/productions/prod-1/rehearsals'), status: 200, body: [] },
    ]);

    renderRouter('src/app', { initialUrl: '/production/prod-1/schedule' });

    await waitFor(() => expect(screen.getByTestId('schedule-create-link')).toBeVisible());
    expect(screen.getByText('＋ タイムテーブルを作成')).toBeVisible();
    expect(screen.getByText('🖨 タイムテーブルを印刷')).toBeVisible();

    fireEvent.press(screen.getByTestId('schedule-create-link'));

    await waitFor(() => expect(screen.getByTestId('timetable-editor')).toBeVisible());
    await waitFor(() => expect(screen.getByTestId('timetable-targets-empty')).toBeVisible());
    expect(screen.queryByTestId('rehearsal-create-link')).toBeNull();
    expect(screen.queryByText('内容を公開する')).toBeNull();
  });
});
