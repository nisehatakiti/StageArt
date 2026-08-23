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
  author_person_id: 'person-2',
  body: '別の人が投稿したコメント',
  created_at: '2026-08-18T09:00:00+09:00',
  updated_at: '2026-08-18T09:00:00+09:00',
};

/** §9: no client-side "hide the button for non-authors" - the Delete
 * control is shown to everyone, and a non-author/non-manager's attempt
 * is rejected by the Backend with a 403 shown inline. */
describe('Attendance detail: Rehearsal comment delete rejected by Backend (403)', () => {
  it('shows the Backend rejection message under the targeted comment', async () => {
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
        const body = {
          code: 'stageart_schedule_comment_access_denied',
          message: 'Only the author, or the PrimaryManager/a REHEARSAL_MANAGER Delegate, can delete this ScheduleComment.',
        };
        return { ok: false, status: 403, text: async () => JSON.stringify(body), json: async () => body } as Response;
      }
      if (url.endsWith('/rehearsals/rehearsal-1/schedule-comments')) {
        return {
          ok: true,
          status: 200,
          text: async () => JSON.stringify([existingComment]),
          json: async () => [existingComment],
        } as Response;
      }

      throw new Error(`Unmocked fetch: ${url}`);
    });

    renderRouter('src/app', { initialUrl: '/production/prod-1/schedule/attendance/rehearsal-1' });

    await waitFor(() => expect(screen.getByTestId('comment-delete-comment-1')).toBeVisible());

    fireEvent.press(screen.getByTestId('comment-delete-comment-1'));

    await waitFor(() => expect(screen.getByTestId('comment-delete-error-comment-1')).toBeVisible());
    expect(screen.getByTestId('comment-delete-error-comment-1')).toHaveTextContent('この情報を表示する権限がありません。');
    // The comment must still be listed - a rejected delete never removes it client-side.
    expect(screen.getByText('別の人が投稿したコメント')).toBeVisible();
  });
});
