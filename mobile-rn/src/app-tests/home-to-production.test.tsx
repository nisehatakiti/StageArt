import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { currentPerson, rehearsalScheduleAdjustment, scheduleAdjustmentRoster } from '@/features/attendance/__fixtures__/attendanceFixtures';
import type { MyDashboard } from '@/types/api';

import { mockFetchRoutes, orgOne, productionOne, projectOne } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

const dashboardWithRehearsal: MyDashboard = {
  upcoming_rehearsals: [
    {
      rehearsal_id: 'rehearsal-1',
      production_id: productionOne.id,
      production_name: productionOne.name,
      title: '通し稽古',
      start_date_time: '2026-08-21T19:00:00+09:00',
      end_date_time: '2026-08-21T21:00:00+09:00',
      location: '○○スタジオ',
      attendance_status: 'UNANSWERED',
      activity_type: 'REHEARSAL',
    },
  ],
  notifications: [],
  followed_organizations_feed: [],
};

/** §24: "Home → Production", "Production → Homeへ戻る" (Back Navigation)
 * in one continuous flow.
 *
 * R7: Home's Production cards (production-card-*, later the Organization
 * list's Productions) are gone - 3113937 (2026-10-06) simplified Home to
 * the Personal Overview. A Production is entered from Home through an
 * upcoming rehearsal of it (its card opens the rehearsal inside the
 * Production's shell), and the shell's explicit back control returns to
 * Home. */
describe('Home -> Production Context -> back to Home', () => {
  it('navigates into a Production from Home, shows its Shell, and returns to Home via the explicit back control', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [orgOne] },
      { test: (u) => u.endsWith('/projects'), status: 200, body: [projectOne] },
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne },
      { test: (u) => u.endsWith('/productions'), status: 200, body: [productionOne] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: dashboardWithRehearsal },
      { test: (u) => u.endsWith('/me/notifications'), status: 200, body: [] },
      { test: (u) => u.endsWith('/me'), status: 200, body: currentPerson },
      { test: (u) => u.endsWith('/rehearsals/rehearsal-1'), status: 200, body: rehearsalScheduleAdjustment },
      { test: (u) => u.includes('/rehearsals/rehearsal-1/attendances'), status: 200, body: scheduleAdjustmentRoster },
      { test: (u) => u.endsWith('/rehearsals/rehearsal-1/schedule-comments'), status: 200, body: [] },
    ]);

    renderRouter('src/app', { initialUrl: '/home' });

    await waitFor(() => expect(screen.getByTestId('upcoming-rehearsal-row-rehearsal-1')).toBeVisible());
    await fireEvent.press(screen.getByTestId('upcoming-rehearsal-row-rehearsal-1'));

    await waitFor(() => expect(screen.getByTestId('back-to-home')).toBeVisible());
    await waitFor(() => expect(screen.getAllByText('○○公演2026').length).toBeGreaterThan(0));

    await fireEvent.press(screen.getByTestId('back-to-home'));

    await waitFor(() => expect(screen.getByTestId('home-screen')).toBeVisible());
    expect(screen.queryByTestId('back-to-home')).toBeNull();
  });
});
