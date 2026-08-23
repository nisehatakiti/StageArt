import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';
import { Alert } from 'react-native';

import { currentPerson, participants, staffItem } from './__fixtures__/scheduleFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

const existingComment = {
  id: 'comment-1',
  rehearsal_id: null,
  timetable_item_id: 'item-staff',
  author_person_id: 'person-1',
  body: '少し遅れます',
  created_at: '2026-08-18T09:00:00+09:00',
  updated_at: '2026-08-18T09:00:00+09:00',
};

/**
 * Phase 6.5: completes the Open Item Phase 6.4 disclosed - Edit/Delete
 * was only wired into the Rehearsal comment screen there. This proves
 * the SAME shared updateScheduleComment/deleteScheduleComment functions
 * and the SAME ScheduleCommentList extension now also drive the
 * TimetableItem-level thread, end-to-end (Alert confirm -> DELETE ->
 * refetch), not merely that the props were passed.
 */
describe('Schedule Item Detail: comment delete', () => {
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

      if (url.endsWith('/me')) {
        return { ok: true, status: 200, text: async () => JSON.stringify(currentPerson), json: async () => currentPerson } as Response;
      }
      if (url.includes('/productions/prod-1/participants')) {
        return { ok: true, status: 200, text: async () => JSON.stringify(participants), json: async () => participants } as Response;
      }
      if (url.includes('/productions/prod-1/timetable-items')) {
        return { ok: true, status: 200, text: async () => JSON.stringify([staffItem]), json: async () => [staffItem] } as Response;
      }
      if (url.endsWith('/timetable-items/item-staff')) {
        return { ok: true, status: 200, text: async () => JSON.stringify(staffItem), json: async () => staffItem } as Response;
      }
      if (url.endsWith('/schedule-comments/comment-1') && init?.method === 'DELETE') {
        deleteCalled = true;
        commentsRemaining = [];
        return { ok: true, status: 204, text: async () => '', json: async () => null } as Response;
      }
      if (url.includes('/timetable-items/item-staff/schedule-comments')) {
        return {
          ok: true,
          status: 200,
          text: async () => JSON.stringify(commentsRemaining),
          json: async () => commentsRemaining,
        } as Response;
      }

      throw new Error(`Unmocked fetch: ${url}`);
    });

    renderRouter('src/app', { initialUrl: '/production/prod-1/schedule/item-staff' });

    await waitFor(() => expect(screen.getByTestId('comment-delete-comment-1')).toBeVisible());

    fireEvent.press(screen.getByTestId('comment-delete-comment-1'));

    await waitFor(() => expect(deleteCalled).toBe(true));
    await waitFor(() => expect(screen.getByTestId('comments-empty')).toBeVisible());
  });
});
