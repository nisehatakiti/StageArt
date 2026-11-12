import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, productionOne } from './__fixtures__/productionShellFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * 参加者向け「公演概要ダッシュボード」instruction: 「参加している公演・活動」
 * からの到達先 `/production/{id}/overview`。GET /productions/{id}/overview
 * (isProductionMember-gated, GetProductionOverviewUseCase.php) で公演基本
 * 情報、GET /me/dashboard の upcoming_rehearsals をこのProduction IDで
 * 絞り込んで次回稽古/出欠確認、GET /me/participating-productions を同様に
 * 絞り込んで本人の参加区分を表示する。稽古作成・タイムテーブル作成/編集/
 * 印刷・受付管理などのProduction管理操作は一切表示しない
 * (schedule/index.tsx 側の既存ボタン文言が決してここに出ないことを確認)。
 */
describe('公演概要ダッシュボード', () => {
  it('shows basic info, the participant\'s own role, and next rehearsal - with no management actions', async () => {
    const overviewProduction = {
      ...productionOne,
      venue_name: '○○ホール',
      schedule_start_date: '2026-10-10',
      schedule_end_date: '2026-10-12',
      description: 'あらすじ本文',
    };

    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne },
      { test: (u) => u.endsWith('/productions/prod-1/overview'), status: 200, body: overviewProduction },
      {
        test: (u) => u.endsWith('/me/dashboard'),
        status: 200,
        body: {
          upcoming_rehearsals: [
            {
              rehearsal_id: 'rehearsal-1',
              production_id: 'prod-1',
              production_name: '○○公演2026',
              title: '通し稽古',
              start_date_time: '2026-10-05T19:00:00+09:00',
              end_date_time: null,
              location: '○○スタジオ',
              attendance_status: 'UNANSWERED',
            },
            {
              rehearsal_id: 'rehearsal-other-production',
              production_id: 'prod-other',
              production_name: '別の公演',
              title: '別公演の稽古',
              start_date_time: '2026-10-01T19:00:00+09:00',
              end_date_time: null,
              location: null,
              attendance_status: 'UNANSWERED',
            },
          ],
          notifications: [],
          followed_organizations_feed: [],
        },
      },
      {
        test: (u) => u.endsWith('/me/participating-productions'),
        status: 200,
        body: [{ participant_id: 'participant-1', production_id: 'prod-1', production_name: '○○公演2026', production_slug: null, participant_type: 'CAST' }],
      },
    ]);

    renderRouter('src/app', { initialUrl: '/production/prod-1/overview' });

    await waitFor(() => expect(screen.getByTestId('production-overview-name')).toBeVisible());
    expect(screen.getByTestId('production-overview-name')).toHaveTextContent('○○公演2026');
    expect(screen.getByTestId('production-overview-venue')).toHaveTextContent('○○ホール');
    expect(screen.getByTestId('production-overview-schedule')).toHaveTextContent('2026-10-10 〜 2026-10-12');
    expect(screen.getByTestId('production-overview-description')).toHaveTextContent('あらすじ本文');

    expect(screen.getByTestId('production-overview-my-participant-type')).toHaveTextContent('出演者');

    // Only this Production's own Rehearsal appears - the other
    // Production's upcoming Rehearsal is filtered out.
    expect(screen.getByTestId('production-overview-rehearsal-row-rehearsal-1')).toBeVisible();
    expect(screen.queryByTestId('production-overview-rehearsal-row-rehearsal-other-production')).toBeNull();
    expect(screen.getByTestId('production-overview-unanswered-dot-rehearsal-1')).toBeVisible();

    // §禁止事項: no Production管理操作 anywhere on this screen.
    expect(screen.queryByText('＋ タイムテーブルを作成')).toBeNull();
    expect(screen.queryByText('🖨 タイムテーブルを印刷')).toBeNull();
    expect(screen.queryByText('＋ 稽古を作成する')).toBeNull();
    expect(screen.queryByText('受付')).toBeNull();
  });
});
