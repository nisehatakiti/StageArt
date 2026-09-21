import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { currentPerson, mockFetchRoutes, participants, staffItem } from './__fixtures__/scheduleFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * StageArt 小屋入り～本番接続 instruction
 * (docs/04-CommonNavigationDesign.md §20.3/§20.6): the タイムテーブル
 * screen (this one - see useNavMenu.ts's production-timetable item) must
 * expose "＋ タイムテーブルを作成" reaching the existing per-Rehearsal
 * authoring flow (出欠/Rehearsal一覧 -> 稽古詳細のタイムテーブル項目追加,
 * unchanged), alongside the pre-existing "🖨 タイムテーブルを印刷" action.
 */
describe('Schedule: タイムテーブルを作成 entry point', () => {
  it('shows "＋ タイムテーブルを作成" and "🖨 タイムテーブルを印刷", and 作成 navigates to the Rehearsal list', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/me'), status: 200, body: currentPerson },
      { test: (u) => u.includes('/productions/prod-1/participants'), status: 200, body: participants },
      { test: (u) => u.includes('/productions/prod-1/timetable-items'), status: 200, body: [staffItem] },
      { test: (u) => u.includes('/productions/prod-1/rehearsals'), status: 200, body: [] },
    ]);

    renderRouter('src/app', { initialUrl: '/production/prod-1/schedule' });

    await waitFor(() => expect(screen.getByTestId('schedule-create-link')).toBeVisible());
    expect(screen.getByText('＋ タイムテーブルを作成')).toBeVisible();
    expect(screen.getByText('🖨 タイムテーブルを印刷')).toBeVisible();

    fireEvent.press(screen.getByTestId('schedule-create-link'));

    await waitFor(() => expect(screen.getByTestId('rehearsal-create-link')).toBeVisible());
  });
});
