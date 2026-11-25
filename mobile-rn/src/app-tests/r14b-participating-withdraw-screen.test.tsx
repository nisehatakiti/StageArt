import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

import { ApiError } from '@/api/errors';

/**
 * PR #7 R14-B (design doc §7.2 / §10, D9; approved test E): the
 * 「参加をやめる」 screen's own behaviour, rendered directly with its data
 * hooks mocked (the full-router harness does not reflect a second state
 * change reliably - see the stage-9 report; r14b-participating-withdraw*.
 * test.tsx keep the full-router checks).
 * - No button on the PrimaryManager's Production.
 * - キャンセル closes the confirmation without calling the API.
 * - After a refusal, another member's withdrawal can still be done.
 * - The API is called with the right participant ids, in order.
 * - The delegate note only for a delegate's Production.
 */
const mockMutateAsync = jest.fn();

jest.mock('expo-router', () => ({ useRouter: () => ({ push: jest.fn() }) }));
jest.mock('@/auth/AuthContext', () => ({ useAuth: () => ({ apiClient: {}, status: 'authenticated' }) }));
jest.mock('@tanstack/react-query', () => ({
  useQuery: () => ({
    data: [
      { id: 'prod-1', is_primary_manager: true, delegate_roles: [] },
      { id: 'prod-2', is_primary_manager: false, delegate_roles: ['REHEARSAL_MANAGER'] },
      { id: 'prod-3', is_primary_manager: false, delegate_roles: [] },
    ],
  }),
}));
jest.mock('@/features/participant/useParticipant', () => ({
  useMyParticipatingProductions: () => ({
    isLoading: false,
    isError: false,
    data: [1, 2, 3].map((n) => ({
      participant_id: `participant-${n}`,
      production_id: `prod-${n}`,
      production_name: `公演${n}`,
      production_slug: null,
      participant_type: 'CAST',
    })),
  }),
  useWithdrawParticipation: () => ({ mutateAsync: mockMutateAsync, isPending: false }),
}));

// eslint-disable-next-line @typescript-eslint/no-require-imports
const ParticipatingProductionsScreen = require('@/app/(app)/participating-productions').default;

describe('参加をやめる (screen)', () => {
  it('cancels without the API, continues after a refusal and calls the API in order', async () => {
    await render(<ParticipatingProductionsScreen />);

    // The PrimaryManager's Production: the hand-over notice, no button.
    expect(screen.queryByTestId('participating-production-withdraw-prod-1')).toBeNull();
    expect(screen.getByTestId('participating-production-primary-manager-prod-1')).toBeTruthy();

    // A delegate's Production: the note; キャンセル closes it without the API.
    await fireEvent.press(screen.getByTestId('participating-production-withdraw-prod-2'));
    expect(screen.getByTestId('participating-production-delegate-note-prod-2')).toHaveTextContent('担当者の役割も解除されます。');
    await fireEvent.press(screen.getByTestId('participating-production-withdraw-cancel-prod-2'));
    expect(screen.queryByTestId('participating-production-confirm-prod-2')).toBeNull();
    expect(mockMutateAsync).not.toHaveBeenCalled();

    // Confirmed, refused (409): the reason is shown on the row.
    await fireEvent.press(screen.getByTestId('participating-production-withdraw-prod-2'));
    mockMutateAsync.mockRejectedValueOnce(new ApiError(409, 'x', 'stageart_participant_withdraw_not_allowed'));
    await fireEvent.press(screen.getByTestId('participating-production-withdraw-confirm-prod-2'));
    await waitFor(() =>
      expect(screen.getByTestId('participating-production-error-prod-2')).toHaveTextContent('現在参加中ではないため、参加をやめる操作はできません。')
    );

    // Another member row afterwards: no delegate note, and it succeeds.
    await fireEvent.press(screen.getByTestId('participating-production-withdraw-prod-3'));
    expect(screen.getByTestId('participating-production-confirm-prod-3')).toBeTruthy();
    expect(screen.queryByTestId('participating-production-delegate-note-prod-3')).toBeNull();
    mockMutateAsync.mockResolvedValueOnce({ participant_id: 'participant-3', production_id: 'prod-3', status: 'WITHDRAWN' });
    await fireEvent.press(screen.getByTestId('participating-production-withdraw-confirm-prod-3'));
    await waitFor(() => expect(screen.getByTestId('participating-productions-withdrawn')).toHaveTextContent('「公演3」の参加をやめました。'));
    expect(screen.queryByTestId('participating-production-confirm-prod-3')).toBeNull();

    expect(mockMutateAsync.mock.calls).toEqual([['participant-2'], ['participant-3']]);
  });
});
