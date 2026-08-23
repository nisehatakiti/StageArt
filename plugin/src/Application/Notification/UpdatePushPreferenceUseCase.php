<?php

declare(strict_types=1);

namespace StageArt\Application\Notification;

use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Domain\Notification\PushPreference;
use StageArt\Domain\Notification\PushPreferenceRepositoryInterface;

/**
 * "本人のみ" (Phase 4 instruction §25) is enforced structurally, not by
 * an explicit ownership check: the target Person is always resolved
 * from the requester's own WordPress user id (get_current_user_id() at
 * the Presentation layer), so there is no id parameter through which a
 * caller could target someone else's preference.
 */
final class UpdatePushPreferenceUseCase
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

    public function execute(UpdatePushPreferenceCommand $command): PushPreferenceResult
    {
        $person = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $person) {
            throw new NotificationAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $preference = $this->preferences->findByPersonId($person->id());

        if (! $preference) {
            $preference = PushPreference::create($person->id(), $command->enabled);
        } else {
            $preference->setEnabled($command->enabled);
        }

        $this->preferences->save($preference);

        return new PushPreferenceResult($preference->enabled(), $preference->updatedAt()->format(DATE_ATOM));
    }
}
