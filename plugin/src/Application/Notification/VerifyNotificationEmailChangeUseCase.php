<?php

declare(strict_types=1);

namespace StageArt\Application\Notification;

use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Domain\Notification\NotificationEmail;
use StageArt\Domain\Notification\NotificationEmailChangeRequestRepositoryInterface;
use StageArt\Domain\Notification\NotificationEmailRepositoryInterface;

/**
 * Public (token-based, no login required) by design, exactly like
 * `VerifyEmailUseCase` - the verification link may be opened on a
 * different device/session than the one that requested the change (see
 * that class's own docblock for the general reasoning this follows).
 *
 * §6/AC-04: only on success does `NotificationEmail` actually change -
 * the candidate email stored on the (now-consumed)
 * `NotificationEmailChangeRequest` is what gets promoted, never
 * anything passed in fresh at this point (there is no email parameter
 * here at all - only a token). §7/AC-07: an unusable token (unknown
 * hash, already consumed, or expired) changes nothing and throws;
 * §9/AC-08 falls naturally out of `NotificationEmailChangeRequest`
 * being replaced-in-place by a newer request - an old token's hash
 * simply no longer matches any stored row.
 */
final class VerifyNotificationEmailChangeUseCase
{
    private NotificationEmailChangeRequestRepositoryInterface $changeRequests;
    private NotificationEmailRepositoryInterface $notificationEmails;
    private TransactionManagerInterface $transactions;

    public function __construct(
        NotificationEmailChangeRequestRepositoryInterface $changeRequests,
        NotificationEmailRepositoryInterface $notificationEmails,
        TransactionManagerInterface $transactions
    ) {
        $this->changeRequests = $changeRequests;
        $this->notificationEmails = $notificationEmails;
        $this->transactions = $transactions;
    }

    public function execute(VerifyNotificationEmailChangeCommand $command): void
    {
        $hash = hash('sha256', $command->token);
        $changeRequest = $this->changeRequests->findByTokenHash($hash);

        if (! $changeRequest || ! $changeRequest->isUsable()) {
            throw new InvalidNotificationEmailChangeTokenException(
                'This notification email change token is invalid, expired, or already used.'
            );
        }

        $this->transactions->run(function () use ($changeRequest): void {
            $notificationEmail = $this->notificationEmails->findByPersonId($changeRequest->personId());

            if ($notificationEmail !== null) {
                $notificationEmail->changeEmail($changeRequest->candidateEmail());
            } else {
                $notificationEmail = NotificationEmail::create(
                    $changeRequest->personId(),
                    $changeRequest->candidateEmail(),
                    true,
                    NotificationEmail::SOURCE_USER
                );
            }

            $this->notificationEmails->save($notificationEmail);

            $changeRequest->consume();
            $this->changeRequests->save($changeRequest);
        });
    }
}
