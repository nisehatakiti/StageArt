<?php

declare(strict_types=1);

namespace StageArt\Domain\Notification;

use StageArt\Domain\Person\PersonId;

interface NotificationEmailChangeRequestRepositoryInterface
{
    public function save(NotificationEmailChangeRequest $request): void;

    /**
     * Returns null when the Person has no pending (or past) change
     * request at all. Callers must still check `isUsable()` themselves -
     * a returned row can be already consumed or expired (kept, not
     * deleted, so Settings can still show "確認待ち" state accurately
     * right up until it lapses).
     */
    public function findByPersonId(PersonId $personId): ?NotificationEmailChangeRequest;

    /**
     * Public/unauthenticated lookup path (the verification link itself
     * carries no session) - mirrors
     * EmailVerificationTokenRepositoryInterface::findByTokenHash()'s own
     * "never look up by the raw token value" contract (this Application
     * layer hashes first, exactly like VerifyEmailUseCase does).
     */
    public function findByTokenHash(string $tokenHash): ?NotificationEmailChangeRequest;
}
