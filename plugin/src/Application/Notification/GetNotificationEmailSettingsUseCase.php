<?php

declare(strict_types=1);

namespace StageArt\Application\Notification;

use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Domain\Notification\NotificationEmailChangeRequestRepositoryInterface;

/**
 * 通知用Email確認・変更機能 §3/AC-01: the Settings screen's own read
 * model - "本人のみ" is structurally true the same way
 * GetPushPreferenceUseCase's is (no id parameter anywhere; the target
 * Person is always resolved from the requester's own WordPress user
 * id), not by an explicit ownership check.
 */
final class GetNotificationEmailSettingsUseCase
{
    private ProductionAuthorizationService $authorization;
    private PersonEmailResolver $emailResolver;
    private NotificationEmailChangeRequestRepositoryInterface $changeRequests;

    public function __construct(
        ProductionAuthorizationService $authorization,
        PersonEmailResolver $emailResolver,
        NotificationEmailChangeRequestRepositoryInterface $changeRequests
    ) {
        $this->authorization = $authorization;
        $this->emailResolver = $emailResolver;
        $this->changeRequests = $changeRequests;
    }

    public function execute(GetNotificationEmailSettingsQuery $query): NotificationEmailSettingsResult
    {
        $person = $this->authorization->resolveCurrentPerson($query->requestedByWordPressUserId);

        if (! $person) {
            throw new NotificationAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $resolution = $this->emailResolver->resolveWithSource($person->id());

        $pendingEmail = null;
        $changeRequest = $this->changeRequests->findByPersonId($person->id());

        if ($changeRequest !== null && $changeRequest->isUsable()) {
            $pendingEmail = $changeRequest->candidateEmail();
        }

        return new NotificationEmailSettingsResult($resolution->email, $resolution->source, $pendingEmail);
    }
}
