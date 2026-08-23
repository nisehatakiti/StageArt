<?php

declare(strict_types=1);

namespace StageArt\Application\Notification;

use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Domain\Notification\PushPreferenceRepositoryInterface;

/**
 * Push Preference has no Production Scope - it is a per-Person setting
 * (Notification.md's "# Push Preference"), so authorization here is
 * simply "does a StageArt Person exist for this WordPress user",
 * reusing ProductionAuthorizationService::resolveCurrentPerson() as the
 * single WP-user -> Person resolution path rather than duplicating it.
 */
final class GetPushPreferenceUseCase
{
    private PushPreferenceRepositoryInterface $preferences;
    private ProductionAuthorizationService $authorization;

    public function __construct(
        PushPreferenceRepositoryInterface $preferences,
        ProductionAuthorizationService $authorization
    ) {
        $this->preferences = $preferences;
        $this->authorization = $authorization;
    }

    public function execute(GetPushPreferenceQuery $query): PushPreferenceResult
    {
        $person = $this->authorization->resolveCurrentPerson($query->requestedByWordPressUserId);

        if (! $person) {
            throw new NotificationAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $preference = $this->preferences->findByPersonId($person->id());

        if (! $preference) {
            return new PushPreferenceResult(true, null);
        }

        return new PushPreferenceResult($preference->enabled(), $preference->updatedAt()->format(DATE_ATOM));
    }
}
