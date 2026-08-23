import { fireEvent, render, screen } from '@testing-library/react-native';

import { ApiError, NetworkError } from '@/api/errors';
import type { ScheduleComment } from '@/types/api';

import { ScheduleCommentList } from './schedule-comment-list';

const comments: ScheduleComment[] = [
  {
    id: 'c1',
    rehearsal_id: null,
    timetable_item_id: 'item-1',
    author_person_id: 'person-1',
    body: '少し遅れます',
    created_at: '2026-08-18T09:00:00+09:00',
    updated_at: '2026-08-18T09:00:00+09:00',
  },
  {
    id: 'c2',
    rehearsal_id: null,
    timetable_item_id: 'item-1',
    author_person_id: 'person-2',
    body: '了解しました',
    created_at: '2026-08-18T09:05:00+09:00',
    updated_at: '2026-08-18T09:05:00+09:00',
  },
];

describe('ScheduleCommentList (§21-23, §22 no Role filtering)', () => {
  it('shows an Empty State when there are no comments', async () => {
    await render(<ScheduleCommentList isLoading={false} isError={false} error={null} comments={[]} />);
    expect(screen.getByTestId('comments-empty')).toBeVisible();
  });

  it('shows an Error State distinct from Empty', async () => {
    await render(<ScheduleCommentList isLoading={false} isError error={new ApiError(403, 'Forbidden')} comments={undefined} />);
    expect(screen.getByTestId('comments-error')).toHaveTextContent('この情報を表示する権限がありません。');
    expect(screen.queryByTestId('comments-empty')).toBeNull();
  });

  it('shows a Network Error message distinctly', async () => {
    await render(<ScheduleCommentList isLoading={false} isError error={new NetworkError(new Error('x'))} comments={undefined} />);
    expect(screen.getByTestId('comments-error')).toHaveTextContent('サーバーへ接続できませんでした。通信環境を確認してください。');
  });

  it('shows every comment unfiltered, with body and author', async () => {
    await render(<ScheduleCommentList isLoading={false} isError={false} error={null} comments={comments} />);

    expect(screen.getByTestId('comment-c1')).toHaveTextContent('少し遅れますperson-1 ・ 2026/08/18 09:00');
    expect(screen.getByTestId('comment-c2')).toHaveTextContent('了解しましたperson-2 ・ 2026/08/18 09:05');
  });

  it('renders no Edit/Delete controls when onEdit/onDelete are omitted (existing TimetableItem usage unaffected)', async () => {
    await render(<ScheduleCommentList isLoading={false} isError={false} error={null} comments={comments} />);

    expect(screen.queryByTestId('comment-edit-c1')).toBeNull();
    expect(screen.queryByTestId('comment-delete-c1')).toBeNull();
  });

  describe('Phase 6.4: Edit / Delete', () => {
    it('shows Edit and Delete controls on every comment when both callbacks are provided', async () => {
      await render(
        <ScheduleCommentList isLoading={false} isError={false} error={null} comments={comments} onEdit={jest.fn()} onDelete={jest.fn()} />
      );

      expect(screen.getByTestId('comment-edit-c1')).toBeVisible();
      expect(screen.getByTestId('comment-delete-c1')).toBeVisible();
      expect(screen.getByTestId('comment-edit-c2')).toBeVisible();
      expect(screen.getByTestId('comment-delete-c2')).toBeVisible();
    });

    it('calls onEdit with the pressed comment', async () => {
      const onEdit = jest.fn();
      await render(<ScheduleCommentList isLoading={false} isError={false} error={null} comments={comments} onEdit={onEdit} />);

      fireEvent.press(screen.getByTestId('comment-edit-c2'));

      expect(onEdit).toHaveBeenCalledWith(comments[1]);
    });

    it('calls onDelete with the pressed comment', async () => {
      const onDelete = jest.fn();
      await render(<ScheduleCommentList isLoading={false} isError={false} error={null} comments={comments} onDelete={onDelete} />);

      fireEvent.press(screen.getByTestId('comment-delete-c1'));

      expect(onDelete).toHaveBeenCalledWith(comments[0]);
    });

    it('renders the edit form with the current draft when editingCommentId matches, hiding the static body', async () => {
      await render(
        <ScheduleCommentList
          isLoading={false}
          isError={false}
          error={null}
          comments={comments}
          onEdit={jest.fn()}
          editingCommentId="c1"
          editDraft="編集中の本文"
        />
      );

      expect(screen.getByTestId('comment-edit-input-c1').props.value).toBe('編集中の本文');
      expect(screen.queryByText('少し遅れます')).toBeNull();
      // The untouched comment still renders normally.
      expect(screen.getByText('了解しました')).toBeVisible();
    });

    it('disables Save while the draft is empty and calls onSaveEdit/onCancelEdit when pressed', async () => {
      const onSaveEdit = jest.fn();
      const onCancelEdit = jest.fn();
      await render(
        <ScheduleCommentList
          isLoading={false}
          isError={false}
          error={null}
          comments={comments}
          editingCommentId="c1"
          editDraft=""
          onSaveEdit={onSaveEdit}
          onCancelEdit={onCancelEdit}
        />
      );

      expect(screen.getByTestId('comment-save-c1').props.accessibilityState?.disabled).toBe(true);

      fireEvent.press(screen.getByTestId('comment-cancel-edit-c1'));
      expect(onCancelEdit).toHaveBeenCalled();
    });

    it('shows editError under the comment currently being edited', async () => {
      await render(
        <ScheduleCommentList
          isLoading={false}
          isError={false}
          error={null}
          comments={comments}
          editingCommentId="c1"
          editDraft="x"
          editError={new ApiError(403, 'Forbidden')}
        />
      );

      expect(screen.getByTestId('comment-edit-error-c1')).toHaveTextContent('この情報を表示する権限がありません。');
    });

    it('shows "削除中…" and disables the button while this comment is the pending delete target', async () => {
      await render(
        <ScheduleCommentList
          isLoading={false}
          isError={false}
          error={null}
          comments={comments}
          onDelete={jest.fn()}
          deleteTargetCommentId="c1"
          isDeletePending
        />
      );

      const button = screen.getByTestId('comment-delete-c1');
      expect(button).toHaveTextContent('削除中…');
      expect(button.props.accessibilityState?.disabled).toBe(true);
      // A different comment's Delete button is unaffected.
      expect(screen.getByTestId('comment-delete-c2')).toHaveTextContent('削除');
    });

    it('shows deleteError under the comment that was targeted, not every comment', async () => {
      await render(
        <ScheduleCommentList
          isLoading={false}
          isError={false}
          error={null}
          comments={comments}
          onDelete={jest.fn()}
          deleteTargetCommentId="c1"
          deleteError={new ApiError(403, 'Forbidden')}
        />
      );

      expect(screen.getByTestId('comment-delete-error-c1')).toHaveTextContent('この情報を表示する権限がありません。');
      expect(screen.queryByTestId('comment-delete-error-c2')).toBeNull();
    });
  });
});
