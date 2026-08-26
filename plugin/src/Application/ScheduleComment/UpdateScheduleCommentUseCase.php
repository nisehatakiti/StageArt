<?php

declare(strict_types=1);

namespace StageArt\Application\ScheduleComment;

use StageArt\Core\Contract\IdentityContract;
use StageArt\Domain\ScheduleComment\ScheduleCommentBody;
use StageArt\Domain\ScheduleComment\ScheduleCommentId;
use StageArt\Domain\ScheduleComment\ScheduleCommentRepositoryInterface;

/**
 * ScheduleComment.md's "Edit and Delete": only the author may edit their
 * own comment - unlike delete, there is no manager-edit-any-comment
 * provision.
 *
 * StageArt Core/Module Architecture Phase 2: depends only on
 * `IdentityContract`, not `ProductionAuthorizationService` directly -
 * this UseCase never needed Production/Authorization at all.
 */
final class UpdateScheduleCommentUseCase
{
    private ScheduleCommentRepositoryInterface $comments;
    private IdentityContract $identity;

    public function __construct(ScheduleCommentRepositoryInterface $comments, IdentityContract $identity)
    {
        $this->comments = $comments;
        $this->identity = $identity;
    }

    public function execute(UpdateScheduleCommentCommand $command): ScheduleCommentResult
    {
        $requesterId = $this->identity->resolveCurrentPersonId($command->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new ScheduleCommentAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $comment = $this->comments->findById(ScheduleCommentId::fromString($command->scheduleCommentId));

        if (! $comment) {
            throw new ScheduleCommentNotFoundException($command->scheduleCommentId);
        }

        if (! $comment->isAuthoredBy($requesterId)) {
            throw new ScheduleCommentAccessDeniedException('Only the author can edit this ScheduleComment.');
        }

        $comment->editBody(new ScheduleCommentBody($command->body));
        $this->comments->save($comment);

        return ScheduleCommentResult::fromDomain($comment);
    }
}
