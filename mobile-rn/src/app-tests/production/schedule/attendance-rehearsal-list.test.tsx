import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, productionOne, rehearsals } from '@/features/attendance/__fixtures__/attendanceFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

describe('Attendance: Rehearsal list', () => {
  it('shows every Rehearsal returned by the API', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne },
      { test: (u) => u.endsWith('/productions/prod-1/rehearsals'), status: 200, body: rehearsals },
    ]);

    renderRouter('src/app', { initialUrl: '/production/prod-1/schedule/attendance' });

    await waitFor(() => expect(screen.getAllByTestId(/rehearsal-row-/)).toHaveLength(2));
    expect(screen.getByText('第1回稽古（日程調整）')).toBeVisible();
    expect(screen.getByText('第2回稽古（出欠確定）')).toBeVisible();

    expect(screen.getByTestId('rehearsal-status-rehearsal-1')).toHaveTextContent('下書き');
    expect(screen.getByTestId('rehearsal-status-rehearsal-2')).toHaveTextContent('確定');
    expect(screen.getAllByText(/10:00〜12:00/)).toHaveLength(2);
    expect(screen.getAllByText('稽古場A')).toHaveLength(2);
  });
});
