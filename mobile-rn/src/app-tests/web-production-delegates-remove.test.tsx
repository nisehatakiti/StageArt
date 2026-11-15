import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty, productionOne } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * ProductionDelegate実用化 instruction §9/§17: 削除 - tapping "担当を解除
 * する" does NOT call the API immediately; it must show a confirmation
 * step first, and only the confirmation button actually calls
 * DELETE /production-delegates/{id}.
 */
describe('Web 担当者: 解除', () => {
  it('requires confirmation before calling DELETE /production-delegates/{id}', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne },
      { test: (u) => u.endsWith('/productions/prod-1/delegates'), status: 200, body: [
        {
          id: 'delegate-1',
          production_id: 'prod-1',
          person_id: 'person-9',
          person_family_name: '山田',
          person_given_name: '太郎',
          role: 'PARTICIPANT_MANAGER',
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

    await waitFor(() => expect(screen.getByTestId('production-delegate-remove-delegate-1')).toBeVisible());

    fireEvent.press(screen.getByTestId('production-delegate-remove-delegate-1'));

    await waitFor(() => expect(screen.getByTestId('production-delegate-confirm-delegate-1')).toBeVisible());

    // Pressing "担当を解除する" alone must never have called DELETE yet.
    expect((global.fetch as jest.Mock).mock.calls.some(([url, init]: [string, RequestInit?]) => init?.method === 'DELETE')).toBe(false);

    (global.fetch as jest.Mock).mockImplementationOnce(async () => ({
      ok: true,
      status: 204,
      text: async () => '',
      json: async () => null,
    }));

    fireEvent.press(screen.getByTestId('production-delegate-confirm-remove-delegate-1'));

    await waitFor(() => {
      const call = (global.fetch as jest.Mock).mock.calls.find(
        ([url, init]: [string, RequestInit?]) => url.endsWith('/production-delegates/delegate-1') && init?.method === 'DELETE'
      );
      expect(call).toBeDefined();
    });
  });
});
