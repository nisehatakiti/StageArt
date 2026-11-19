import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { expenseFixture, mockFetchRoutes, patchCalls } from './__fixtures__/expenseFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));


/**
 * A plain participant (the Production itself is refused, 403) opens their
 * rejected Expense from the rejection notification: the reason and the
 * history are shown, and they can resubmit.
 */
describe('Expense detail: the applicant of a rejected Expense', () => {
  it('shows why it was rejected and resubmits it', async () => {
    const rejected = expenseFixture({
      status: 'REJECTED',
      created_by: 'person-1',
      submitted_by: 'person-1',
      rejection_reason: '金額を確認してください',
      reviews: [
        ...expenseFixture().reviews,
        {
          revision: 1,
          action: 'REJECTED',
          requested_amount: 5000,
          approved_amount: null,
          lines: [],
          reason: '金額を確認してください',
          actor_person_id: 'person-owner',
          acted_at: '2026-10-21T09:00:00+00:00',
        },
      ],
    });
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 403, body: { code: 'stageart_production_access_denied', message: 'Forbidden' } },
      { test: (u) => u.endsWith('/expenses/exp-1/resubmit'), status: 200, body: expenseFixture({ created_by: 'person-1', submitted_by: 'person-1', revision: 2 }) },
      { test: (u) => u.endsWith('/expenses/exp-1'), status: 200, body: rejected },
    ]);

    renderRouter('src/app', { initialUrl: '/expenses/exp-1' });

    await waitFor(() => expect(screen.getByTestId('expense-rejection-reason-value')).toHaveTextContent('金額を確認してください'));
    expect(screen.getByTestId('expense-history-1')).toHaveTextContent(/第1回 却下/);
    expect(screen.getByTestId('expense-history-1')).toHaveTextContent(/理由：金額を確認してください/);
    expect(screen.queryByTestId('expense-review-actions')).toBeNull();

    fireEvent.press(screen.getByTestId('expense-resubmit'));

    await waitFor(() => expect(patchCalls('/expenses/exp-1/resubmit')).toHaveLength(1));
    await waitFor(() => expect(screen.getByTestId('expense-status-value')).toHaveTextContent('申請中'));
  });
});
