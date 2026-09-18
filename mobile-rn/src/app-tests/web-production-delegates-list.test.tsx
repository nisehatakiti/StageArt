import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty, productionOne } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * ProductionDelegate実用化 instruction §17: 一覧 - a real delegate row
 * renders with its Backend-resolved person_family_name/person_given_name
 * (not a raw personId), and its current Role/Status.
 */
describe('Web 担当者: 一覧', () => {
  it('lists an existing delegate with the resolved person name and role', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne },
      { test: (u) => u.endsWith('/productions/prod-1/delegates'), status: 200, body: [
        {
          id: 'delegate-1',
          production_id: 'prod-1',
          person_id: 'person-9',
          person_family_name: '山田',
          person_given_name: '太郎',
          role: 'QUESTIONNAIRE_MANAGER',
          status: 'ACTIVE',
          created_by: 'person-1',
          created_at: '',
          updated_by: 'person-1',
          updated_at: '',
        },
      ] },
      { test: (u) => u.endsWith('/productions/prod-1/participants'), status: 200, body: [] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/productions/prod-1/delegates' });

    await waitFor(() => expect(screen.getByTestId('production-delegate-row-delegate-1')).toBeVisible());
    expect(screen.getByText('山田 太郎')).toBeVisible();
    expect(screen.getByTestId('production-delegate-role-delegate-1-QUESTIONNAIRE_MANAGER')).toBeVisible();
  });
});
