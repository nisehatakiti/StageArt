<?php

declare(strict_types=1);

namespace StageArt\Application\ScheduleComment;

use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Domain\ScheduleComment\ScheduleCommentBody;
use StageArt\Domain\ScheduleComment\ScheduleCommentId;
use StageArt\Domain\ScheduleComment\ScheduleCommentRepositoryInterface;

/**
 * ScheduleComment.md's "Edit and Delete": only the author may edit their
 * own comment - unlike delete, there is no manager-edit-any-comment
 * provision.
 */
final class UpdateScheduleCommentUseCase
{
    private ScheduleCommentRepositoryInterface $comments;
    private ProductionAuthorizationService $authorization;

    public function __construct(ScheduleCommentRepositoryInterface $comments, ProductionAuthorizationService $authorization)
    {
        $this->comments = $comments;
        $this->authorization = $authorization;
    }

    public function execute(UpdateScheduleCommentCommand $command): ScheduleCommentResult
    {
        $requester = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $requester) {
            throw new ScheduleCommentAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $comment = $this->comments->findById(ScheduleCommentId::fromString($command->scheduleCommentId));

        if (! $comment) {
            throw new ScheduleCommentNotFoundException($command->scheduleCommentId);
        }

        if (! $comment->isAuthoredBy($requester->id())) {
            throw new ScheduleCommentAccessDeniedException('Only the author can edit this ScheduleComment.');
        }

        $comment->editBody(new ScheduleCommentBody($command->body));
        $this->comments->save($comment);

        return ScheduleCommentResult::fromDomain($comment);
    }
}
