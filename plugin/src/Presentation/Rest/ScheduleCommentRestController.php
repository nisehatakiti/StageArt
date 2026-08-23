<?php

declare(strict_types=1);

namespace StageArt\Presentation\Rest;

use InvalidArgumentException;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Rehearsal\RehearsalNotFoundException;
use StageArt\Application\ScheduleComment\CreateScheduleCommentCommand;
use StageArt\Application\ScheduleComment\CreateScheduleCommentUseCase;
use StageArt\Application\ScheduleComment\CreateTimetableItemScheduleCommentCommand;
use StageArt\Application\ScheduleComment\CreateTimetableItemScheduleCommentUseCase;
use StageArt\Application\ScheduleComment\DeleteScheduleCommentCommand;
use StageArt\Application\ScheduleComment\DeleteScheduleCommentUseCase;
use StageArt\Application\ScheduleComment\ListScheduleCommentsForRehearsalQuery;
use StageArt\Application\ScheduleComment\ListScheduleCommentsUseCase;
use StageArt\Application\ScheduleComment\ListTimetableItemScheduleCommentsQuery;
use StageArt\Application\ScheduleComment\ListTimetableItemScheduleCommentsUseCase;
use StageArt\Application\ScheduleComment\ScheduleCommentAccessDeniedException;
use StageArt\Application\ScheduleComment\ScheduleCommentNotFoundException;
use StageArt\Application\ScheduleComment\UpdateScheduleCommentCommand;
use StageArt\Application\ScheduleComment\UpdateScheduleCommentUseCase;
use StageArt\Application\Timetable\TimetableNotFoundException;
use StageArt\Application\TimetableItem\TimetableItemNotFoundException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class ScheduleCommentRestController
{
    private const API_NAMESPACE = 'stageart/v1';

    private CreateScheduleCommentUseCase $createComment;
    private ListScheduleCommentsUseCase $listComments;
    private UpdateScheduleCommentUseCase $updateComment;
    private DeleteScheduleCommentUseCase $deleteComment;
    private CreateTimetableItemScheduleCommentUseCase $createTimetableItemComment;
    private ListTimetableItemScheduleCommentsUseCase $listTimetableItemComments;

    public function __construct(
        CreateScheduleCommentUseCase $createComment,
        ListScheduleCommentsUseCase $listComments,
        UpdateScheduleCommentUseCase $updateComment,
        DeleteScheduleCommentUseCase $deleteComment,
        CreateTimetableItemScheduleCommentUseCase $createTimetableItemComment,
        ListTimetableItemScheduleCommentsUseCase $listTimetableItemComments
    ) {
        $this->createComment = $createComment;
        $this->listComments = $listComments;
        $this->updateComment = $updateComment;
        $this->deleteComment = $deleteComment;
        $this->createTimetableItemComment = $createTimetableItemComment;
        $this->listTimetableItemComments = $listTimetableItemComments;
    }

    public function register_routes(): void
    {
        register_rest_route(self::API_NAMESPACE, '/rehearsals/(?P<id>[^/]+)/schedule-comments', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'list'],
                'permission_callback' => [$this, 'require_login'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/timetable-items/(?P<id>[^/]+)/schedule-comments', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'listForTimetableItem'],
                'permission_callback' => [$this, 'require_login'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'createForTimetableItem'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/schedule-comments/(?P<id>[^/]+)', [
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update'],
                'permission_callback' => [$this, 'require_login'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);
    }

    public function require_login(): bool
    {
        return is_user_logged_in();
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function list(WP_REST_Request $request)
    {
        try {
            $query = new ListScheduleCommentsForRehearsalQuery((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response(
                array_map(static fn ($result) => $result->toArray(), $this->listComments->execute($query)),
                200
            );
        } catch (ScheduleCommentAccessDeniedException $exception) {
            return new WP_Error('stageart_schedule_comment_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (RehearsalNotFoundException $exception) {
            return new WP_Error('stageart_rehearsal_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_schedule_comment_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function create(WP_REST_Request $request)
    {
        try {
            $command = new CreateScheduleCommentCommand(
                (string) $request->get_param('id'),
                get_current_user_id(),
                (string) $request->get_param('body')
            );

            return new WP_REST_Response($this->createComment->execute($command)->toArray(), 201);
        } catch (ScheduleCommentAccessDeniedException $exception) {
            return new WP_Error('stageart_schedule_comment_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (RehearsalNotFoundException $exception) {
            return new WP_Error('stageart_rehearsal_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_schedule_comment_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function listForTimetableItem(WP_REST_Request $request)
    {
        try {
            $query = new ListTimetableItemScheduleCommentsQuery((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response(
                array_map(static fn ($result) => $result->toArray(), $this->listTimetableItemComments->execute($query)),
                200
            );
        } catch (ScheduleCommentAccessDeniedException $exception) {
            return new WP_Error('stageart_schedule_comment_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (TimetableItemNotFoundException $exception) {
            return new WP_Error('stageart_timetable_item_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (TimetableNotFoundException $exception) {
            return new WP_Error('stageart_timetable_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (RehearsalNotFoundException $exception) {
            return new WP_Error('stageart_rehearsal_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_schedule_comment_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function createForTimetableItem(WP_REST_Request $request)
    {
        try {
            $command = new CreateTimetableItemScheduleCommentCommand(
                (string) $request->get_param('id'),
                get_current_user_id(),
                (string) $request->get_param('body')
            );

            return new WP_REST_Response($this->createTimetableItemComment->execute($command)->toArray(), 201);
        } catch (ScheduleCommentAccessDeniedException $exception) {
            return new WP_Error('stageart_schedule_comment_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (TimetableItemNotFoundException $exception) {
            return new WP_Error('stageart_timetable_item_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (TimetableNotFoundException $exception) {
            return new WP_Error('stageart_timetable_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (RehearsalNotFoundException $exception) {
            return new WP_Error('stageart_rehearsal_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_schedule_comment_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function update(WP_REST_Request $request)
    {
        try {
            $command = new UpdateScheduleCommentCommand(
                (string) $request->get_param('id'),
                get_current_user_id(),
                (string) $request->get_param('body')
            );

            return new WP_REST_Response($this->updateComment->execute($command)->toArray(), 200);
        } catch (ScheduleCommentAccessDeniedException $exception) {
            return new WP_Error('stageart_schedule_comment_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ScheduleCommentNotFoundException $exception) {
            return new WP_Error('stageart_schedule_comment_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_schedule_comment_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function delete(WP_REST_Request $request)
    {
        try {
            $command = new DeleteScheduleCommentCommand((string) $request->get_param('id'), get_current_user_id());
            $this->deleteComment->execute($command);

            return new WP_REST_Response(null, 204);
        } catch (ScheduleCommentAccessDeniedException $exception) {
            return new WP_Error('stageart_schedule_comment_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ScheduleCommentNotFoundException $exception) {
            return new WP_Error('stageart_schedule_comment_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (TimetableItemNotFoundException $exception) {
            return new WP_Error('stageart_timetable_item_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (TimetableNotFoundException $exception) {
            return new WP_Error('stageart_timetable_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (RehearsalNotFoundException $exception) {
            return new WP_Error('stageart_rehearsal_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        }
    }
}
