import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';
import { Alert } from 'react-native';

import {
  currentPerson,
  productionOne,
  rehearsalScheduleAdjustment,
  scheduleAdjustmentRoster,
} from '@/features/attendance/__fixtures__/attendanceFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

const existingComment = {
  id: 'comment-1',
  rehearsal_id: 'rehearsal-1',
  timetable_item_id: null,
  author_person_id: 'person-1',
  body: '集合場所はどこですか？',
  created_at: '2026-08-18T09:00:00+09:00',
  updated_at: '2026-08-18T09:00:00+09:00',
};

/** §8: delete requires confirmation via Alert - mocked to auto-confirm,
 * matching the established pattern in mypage-logout.test.tsx. Verifies
 * the DELETE request is actually sent and the list reflects removal
 * after the Query invalidation/refetch, not merely that a button exists. */
describe('Attendance detail: Rehearsal comment delete', () => {
  it('confirms via Alert, sends DELETE, and the comment is gone after refetch', async () => {
    let deleteCalled = false;
    let commentsRemaining = [existingComment];

    jest.spyOn(Alert, 'alert').mockImplementation((_title, _message, buttons) => {
      const destructive = buttons?.find((button) => button.style === 'destructive');
      destructive?.onPress?.();
    });

    global.fetch = jest.fn(async (input: unknown, init?: RequestInit) => {
      const url = String(input);

      if (url.endsWith('/auth/refresh')) {
        return {
          ok: true,
          status: 200,
          text: async () => JSON.stringify({ access_token: 'refreshed-token', token_type: 'Bearer', expires_in: 3600 }),
        } as Response;
      }

      if (url.endsWith('/productions/prod-1')) {
        return { ok: true, status: 200, text: async () => JSON.stringify(productionOne), json: async () => productionOne } as Response;
      }
      if (url.endsWith('/me')) {
        return { ok: true, status: 200, text: async () => JSON.stringify(currentPerson), json: async () => currentPerson } as Response;
      }
      if (url.endsWith('/rehearsals/rehearsal-1')) {
        return {
          ok: true,
          status: 200,
          text: async () => JSON.stringify(rehearsalScheduleAdjustment),
          json: async () => rehearsalScheduleAdjustment,
        } as Response;
      }
      if (url.includes('/rehearsals/rehearsal-1/attendances')) {
        return {
          ok: true,
          status: 200,
          text: async () => JSON.stringify(scheduleAdjustmentRoster),
          json: async () => scheduleAdjustmentRoster,
        } as Response;
      }
      if (url.endsWith('/schedule-comments/comment-1') && init?.method === 'DELETE') {
        deleteCalled = true;
        commentsRemaining = [];
        return { ok: true, status: 204, text: async () => '', json: async () => null } as Response;
      }
      if (url.endsWith('/rehearsals/rehearsal-1/schedule-comments')) {
        return {
          ok: true,
          status: 200,
          text: async () => JSON.stringify(commentsRemaining),
          json: async () => commentsRemaining,
        } as Response;
      }

      throw new Error(`Unmocked fetch: ${url}`);
    });

    renderRouter('src/app', { initialUrl: '/production/prod-1/schedule/attendance/rehearsal-1' });

    await waitFor(() => expect(screen.getByTestId('comment-delete-comment-1')).toBeVisible());

    fireEvent.press(screen.getByTestId('comment-delete-comment-1'));

    await waitFor(() => expect(deleteCalled).toBe(true));
    await waitFor(() => expect(screen.getByTestId('comments-empty')).toBeVisible());
  });
});
