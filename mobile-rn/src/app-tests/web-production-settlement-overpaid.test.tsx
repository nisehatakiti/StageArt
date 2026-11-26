import { Alert } from 'react-native';
import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

const baseProduction = {
  id: 'prod-1',
  project_id: 'proj-1',
  name: '第10回公演',
  title_heading: null,
  status: 'ACTIVE',
  slug: 'prod-1-slug',
  published_at: null,
  primary_manager_person_id: 'person-1',
  created_at: '',
  updated_at: '',
  is_primary_manager: true,
  delegate_role: null,
  description: null,
  description_published_at: null,
  flyer_url: null,
  flyer_published_at: null,
  venue_name: null,
  venue_published_at: null,
  schedule_start_date: null,
  schedule_end_date: null,
  schedule_published_at: null,
  script_credit: null,
  direction_credit: null,
  script_direction_published_at: null,
  member_info_published_at: null,
  capacity: null,
  performance_common_remarks: null,
};

const project = { id: 'proj-1', organization_id: 'org-1', name: null, status: 'ACTIVE', created_at: '', updated_at: '', current_person_role: 'OWNER' };

/** A member settled 300 for one seat whose Check-in was refunded afterwards. */
const overpaidSummary = {
  members: [
    {
      person_id: 'person-2',
      display_name: '架空 メンバー',
      confirmed_ticket_back_amount: 0,
      already_settled_amount: 300,
      outstanding_amount: 0,
      overpaid_amount: 300,
      last_settled_amount: 300,
    },
  ],
  quota_shortfall_count: 0,
  quota_shortfall_payable: 0,
};

/**
 * PR #7 review (R7): a settlement over-paid by a refund after settling is
 * shown on the 精算 screen, and Production Completion's refusal (422
 * stageart_production_settlement_overpaid) is explained with the amount.
 */
describe('Web 精算: 精算後の返金による払い過ぎ', () => {
  it('shows the over-payment and explains the refused completion', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [{ id: 'org-1', name: '○○演劇団', accounting_enabled: false }] },
      { test: (u) => u.endsWith('/projects'), status: 200, body: [project] },
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: baseProduction },
      { test: (u) => u.endsWith('/productions/prod-1/settlement'), status: 200, body: overpaidSummary },
    ]);
    jest.spyOn(Alert, 'alert').mockImplementation((_title, _message, buttons) => {
      buttons?.find((b) => b.text === 'OK')?.onPress?.();
    });

    renderRouter('src/app', { initialUrl: '/productions/prod-1/settlement' });

    await waitFor(() => expect(screen.getByTestId('settlement-overpaid-person-2')).toBeVisible());
    expect(screen.getByTestId('settlement-overpaid-person-2')).toHaveTextContent(/払い過ぎ: 300円/);

    const refusal = {
      code: 'stageart_production_settlement_overpaid',
      message: 'This Production cannot be completed: 1 member(s) were settled 300 more than their confirmed Ticket Back.',
      data: {
        status: 422,
        total_overpaid_amount: 300,
        members: [{ person_id: 'person-2', confirmed_ticket_back_amount: 0, settled_amount: 300, overpaid_amount: 300 }],
      },
    };
    (global.fetch as jest.Mock).mockImplementationOnce(async () => ({
      ok: false,
      status: 422,
      text: async () => JSON.stringify(refusal),
      json: async () => refusal,
    }));

    fireEvent.press(screen.getByTestId('production-settlement-complete'));

    await waitFor(() => expect(screen.getByTestId('production-settlement-lifecycle-error')).toBeVisible());
    expect(screen.getByTestId('production-settlement-lifecycle-error')).toHaveTextContent(
      '精算済みの額が確定したチケットバックを上回っているメンバーがいるため（合計300円）、決算を完了できません。精算画面で該当メンバーの精算を取り消し、確定額で精算し直してください。'
    );
  });
});
