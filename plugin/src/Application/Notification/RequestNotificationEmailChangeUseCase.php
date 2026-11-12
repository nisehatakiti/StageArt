<?php

declare(strict_types=1);

namespace StageArt\Application\Notification;

use DateInterval;
use DateTimeImmutable;
use StageArt\Application\Authentication\AuthMailerInterface;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Domain\Notification\NotificationEmailChangeRequest;
use StageArt\Domain\Notification\NotificationEmailChangeRequestRepositoryInterface;

/**
 * 通知用Email確認・変更機能 §4/§9/§10/AC-02/AC-03: requesting a change
 * NEVER touches `NotificationEmail` itself (仕様書 §4's explicit
 * "Email入力時点ではNotificationEmailを変更しないでください") - it only
 * ever writes a pending `NotificationEmailChangeRequest`, which a
 * verification link later promotes (see
 * VerifyNotificationEmailChangeUseCase). A new request always REPLACES
 * any previous pending one for the same Person (single row, unique on
 * person_id - see NotificationEmailChangeRequest::replaceWith()'s own
 * docblock), so an old, unconsumed verification link stops working the
 * instant a newer request is made (仕様書 §9).
 *
 * §10: requesting the CURRENT effective email (whatever
 * PersonEmailResolver would already resolve today) is a no-op - no
 * token is created, no email is sent, matching
 * RequestEmailVerificationUseCase's own "already satisfied" idempotency
 * reasoning.
 */
final class RequestNotificationEmailChangeUseCase
{
    private const TOKEN_LIFETIME = 'PT24H';

    private ProductionAuthorizationService $authorization;
    private PersonEmailResolver $emailResolver;
    private NotificationEmailChangeRequestRepositoryInterface $changeRequests;
    private AuthMailerInterface $mailer;
    private TransactionManagerInterface $transactions;

    public function __construct(
        ProductionAuthorizationService $authorization,
        PersonEmailResolver $emailResolver,
        NotificationEmailChangeRequestRepositoryInterface $changeRequests,
        AuthMailerInterface $mailer,
        TransactionManagerInterface $transactions
    ) {
        $this->authorization = $authorization;
        $this->emailResolver = $emailResolver;
        $this->changeRequests = $changeRequests;
        $this->mailer = $mailer;
        $this->transactions = $transactions;
    }

    public function execute(RequestNotificationEmailChangeCommand $command): RequestNotificationEmailChangeResult
    {
        $email = trim($command->email);

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidNotificationEmailException("Invalid email address: {$command->email}");
        }

        $person = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $person) {
            throw new NotificationAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $currentEmail = $this->emailResolver->resolve($person->id());

        if ($currentEmail !== null && strcasecmp($currentEmail, $email) === 0) {
            return new RequestNotificationEmailChangeResult(RequestNotificationEmailChangeResult::STATUS_ALREADY_CURRENT);
        }

        $this->transactions->run(function () use ($person, $email): void {
            $tokenValue = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $tokenValue);
            $expiresAt = (new DateTimeImmutable())->add(new DateInterval(self::TOKEN_LIFETIME));

            $changeRequest = $this->changeRequests->findByPersonId($person->id());

            if ($changeRequest !== null) {
                $changeRequest->replaceWith($email, $tokenHash, $expiresAt);
            } else {
                $changeRequest = NotificationEmailChangeRequest::create($person->id(), $email, $tokenHash, $expiresAt);
            }

            $this->changeRequests->save($changeRequest);

            $this->mailer->sendNotificationEmailChangeVerificationEmail($email, $tokenValue);
        });

        return new RequestNotificationEmailChangeResult(RequestNotificationEmailChangeResult::STATUS_PENDING_VERIFICATION);
    }
}
