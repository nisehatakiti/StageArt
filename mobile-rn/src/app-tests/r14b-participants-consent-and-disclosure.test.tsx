import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty, productionOne } from './__fixtures__/homeFixtures';
import { apiAnswer, apiError, callsTo, contains, managedInvitation, participantRow } from './__fixtures__/r14bFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * PR #7 R14-B stage 9 (design doc §4.5 / §7.3 / §10 / §14.2):
 * - 「同意未確認（旧方式）」 and 「同意の確認を依頼」, which sends an ordinary
 *   invitation to the member's address (disabled when the address is not
 *   known);
 * - fields the Backend withholds (null) are shown as empty, never as
 *   "null", and a row whose Person id is withheld cannot get delegate roles;
 * - 更新 sends no `status`; a 409 type_conflict shows the §10 text.
 */
describe('R14-B メンバー管理: 同意の確認と個人情報の表示', () => {
  it('requests consent, shows withheld fields as empty and updates without status', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: { ...productionOne, is_primary_manager: true } },
      {
        test: (u) => u.endsWith('/productions/prod-1/participants'),
        status: 200,
        body: [
          participantRow({ id: 'p-legacy', subject_id: 'person-7', display_name: '旧方式 次郎', email: 'legacy@example.com', enrollment_source: null, consent_status: 'UNCONFIRMED', consented_at: null }),
          participantRow({ id: 'p-noemail', subject_id: 'person-6', display_name: 'メール 不明', email: null, enrollment_source: 'JOIN_REQUEST', consent_status: 'UNCONFIRMED', consented_at: null }),
          participantRow({ id: 'p-withheld', subject_id: null, display_name: '情報 制限', person_family_name: null, person_given_name: null, email: null, consent_status: null, enrollment_source: null, consented_at: null }),
          participantRow({ id: 'p-ended', status: 'WITHDRAWN', subject_id: null, display_name: '辞退 三郎', email: null, remarks: null }),
        ],
      },
      { test: (u) => u.endsWith('/productions/prod-1/participation-requests'), status: 200, body: [] },
      { test: (u) => u.endsWith('/productions/prod-1/participant-invitations'), status: 200, body: [] },
      { test: (u) => u.endsWith('/productions/prod-1/delegates'), status: 200, body: [] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/productions/prod-1/participants' });

    await waitFor(() => expect(screen.getByTestId('participant-row-p-legacy')).toBeVisible());

    // §4.5: the consent state from the Backend.
    expect(screen.getByTestId('participant-consent-p-legacy')).toHaveTextContent('同意未確認（旧方式）');
    expect(screen.getByTestId('participant-consent-p-noemail')).toHaveTextContent('同意未確認');
    expect(screen.queryByTestId('participant-consent-p-withheld')).toBeNull();

    // §7.3: withheld values are empty, never the text "null"; the ended row is not in the roster.
    expect(screen.getByTestId('participant-email-p-withheld')).toHaveTextContent('');
    expect(screen.queryByText(/null/)).toBeNull();
    expect(screen.queryByTestId('participant-row-p-ended')).toBeNull();
    expect(screen.getByTestId('participant-delegate-proxy-p-withheld').props.accessibilityState?.disabled).toBe(true);

    // No address known: the request cannot be sent.
    expect(screen.getByTestId('participant-request-consent-p-noemail').props.accessibilityState?.disabled).toBe(true);
    expect(screen.getByText('メールアドレスが不明です')).toBeVisible();

    // 「同意の確認を依頼」: an ordinary invitation to the member's address.
    (global.fetch as jest.Mock).mockImplementationOnce(apiAnswer(201, { outcome: 'INVITATION_SENT', invitation: managedInvitation({ email: 'legacy@example.com' }) }));
    fireEvent.press(screen.getByTestId('participant-request-consent-p-legacy'));
    await waitFor(() => expect(callsTo('/productions/prod-1/participant-invitations', 'POST')).toHaveLength(1));
    const invite = JSON.parse(callsTo('/productions/prod-1/participant-invitations', 'POST')[0][1].body as string);
    expect(invite).toEqual({ name: '旧方式 次郎', email: 'legacy@example.com', participant_type: 'CAST', remarks: null });
    await waitFor(() =>
      expect(screen.getByTestId('production-participants-add-member-message')).toHaveTextContent(
        '同意の確認を依頼する招待メールを送信しました。本人が承諾すると同意が記録されます。'
      )
    );

    // 更新: role / remarks only - status is never sent (N6); a type conflict shows the §10 text.
    fireEvent.press(screen.getByTestId('participant-type-p-legacy-STAFF'));
    await waitFor(() => expect(screen.getByTestId('participant-type-p-legacy-STAFF').props.accessibilityState?.selected).toBe(true));
    (global.fetch as jest.Mock).mockImplementationOnce(apiError(409, 'stageart_participant_type_conflict'));
    fireEvent.press(screen.getByTestId('production-participants-save'));

    await waitFor(() => expect(callsTo('/participants/p-legacy', 'PUT')).toHaveLength(1));
    const put = JSON.parse(callsTo('/participants/p-legacy', 'PUT')[0][1].body as string);
    expect(put).toEqual({ participant_type: 'STAFF', remarks: null });
    expect(put).not.toHaveProperty('status');
    await waitFor(() =>
      expect(screen.getByTestId('production-participants-error')).toHaveTextContent(contains('この人は、変更先の区分で既にこの公演に登録されています。'))
    );
  });
});
