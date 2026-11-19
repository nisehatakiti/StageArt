import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, productionOne, rehearsals } from '@/features/attendance/__fixtures__/attendanceFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * A REHEARSAL_MANAGER delegate (not the PrimaryManager) is one of the two
 * kinds of Person CreateRehearsalUseCase accepts, so 「＋ 予定を作成する」 is offered.
 */
describe('予定: 稽古担当（REHEARSAL_MANAGER）', () => {
  it('offers 予定を作成する', async () => {
    const rehearsalManager = { ...productionOne, is_primary_manager: false, delegate_role: 'REHEARSAL_MANAGER', delegate_roles: ['REHEARSAL_MANAGER'] };
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: rehearsalManager },
      { test: (u) => u.endsWith('/productions/prod-1/rehearsals'), status: 200, body: rehearsals },
    ]);

    renderRouter('src/app', { initialUrl: '/production/prod-1/schedule/attendance' });

    await waitFor(() => expect(screen.getByTestId('rehearsal-create-link')).toBeVisible());
    expect(screen.getByTestId(`rehearsal-row-${rehearsals[0].id}`)).toBeVisible();
  });
});
