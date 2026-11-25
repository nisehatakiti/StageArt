<?php

declare(strict_types=1);

namespace StageArt\Application\ParticipantInvitation;

use DateInterval;
use DateTimeImmutable;
use StageArt\Application\Participant\ParticipantEventRecorder;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Domain\ParticipantInvitation\InvitationEmailAddress;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitation;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationRepositoryInterface;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationSend;
use StageArt\Domain\ParticipantInvitation\ParticipantInvitationSendRepositoryInterface;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;
use Throwable;

/**
 * R14-B §5.3 / §6.2 / §6.7 / §11.3 (stage 4): the one place that counts,
 * records and delivers invitation e-mails, for both the create and the
 * resend API.
 *
 * Inside the caller's transaction (after the Production -> invitation
 * locks, §5.4):
 * - assertWithinLimit(): at most MAX_SENDS_PER_ADDRESS sends to the same
 *   Production + normalised address within 30 days, all invitations and
 *   types together (cancelled / expired / rejected / SUPERSEDED ones
 *   count). The address is matched by email_key = HMAC-SHA-256(key,
 *   address) for every key version used in the window (§6.7); a missing
 *   version or no current version refuses the send
 *   (EmailKeyNotConfiguredException) - never count too few, never fall
 *   back to an unkeyed hash. Over the limit: SendLimitException with
 *   `available_at`.
 * - accept(): adds the PENDING send record - the send is counted now, and
 *   is never uncounted - points the invitation's current_send_id at it and
 *   records INVITED / RESENT.
 *
 * After the commit, deliver(): reads current_send_id again (no lock); if a
 * newer attempt was accepted meanwhile, no e-mail is sent and the attempt
 * is SUPERSEDED. Otherwise the e-mail is sent and the result recorded in a
 * short transaction of its own - only on this attempt's row and only while
 * it is PENDING, so a result is never recorded twice and an older attempt
 * never overwrites a newer one; FAILED also records SEND_FAILED. A failed
 * send never rolls the invitation back or uncounts it. Nothing here logs
 * an address, a key or a token.
 */
final class InvitationSendService
{
    public const MAX_SENDS_PER_ADDRESS = 6;
    public const WINDOW = 'P30D';

    private ParticipantInvitationSendRepositoryInterface $sends;
    private ParticipantInvitationRepositoryInterface $invitations;
    private EmailKeyringInterface $keyring;
    private ParticipantInvitationMailerInterface $mailer;
    private ParticipantEventRecorder $events;
    private TransactionManagerInterface $transactions;

    public function __construct(
        ParticipantInvitationSendRepositoryInterface $sends,
        ParticipantInvitationRepositoryInterface $invitations,
        EmailKeyringInterface $keyring,
        ParticipantInvitationMailerInterface $mailer,
        ParticipantEventRecorder $events,
        TransactionManagerInterface $transactions
    ) {
        $this->sends = $sends;
        $this->invitations = $invitations;
        $this->keyring = $keyring;
        $this->mailer = $mailer;
        $this->events = $events;
        $this->transactions = $transactions;
    }

    /**
     * @return array{0: string, 1: string} the current key version and this address's email_key under it
     *
     * @throws ParticipantInvitationSendLimitException
     * @throws EmailKeyNotConfiguredException
     */
    public function assertWithinLimit(ProductionId $productionId, string $email, DateTimeImmutable $now): array
    {
        $current = $this->keyring->currentVersion();

        if ($current === null) {
            throw new EmailKeyNotConfiguredException('No current email key version is configured.');
        }

        $since = $now->sub(new DateInterval(self::WINDOW));
        $normalized = InvitationEmailAddress::normalize($email);
        $keys = [];

        foreach (array_unique(array_merge($this->sends->keyVersionsSince($productionId, $since), [$current])) as $version) {
            $key = $this->keyring->keyFor((string) $version);

            if ($key === null) {
                throw new EmailKeyNotConfiguredException("The email key version '{$version}' used within the last 30 days is not configured.");
            }

            $keys[(string) $version] = self::emailKey($normalized, $key);
        }

        $limit = InvitationSendLimit::fromRequestedAts($this->sends->requestedAtsForKeysSince($productionId, $since, $keys));

        if (! $limit->allowsAnotherSend()) {
            throw new ParticipantInvitationSendLimitException($limit->availableAt);
        }

        return [$current, $keys[$current]];
    }

    /**
     * §7.1 managers' list (R14B-4R): the 30-day send limit of each address,
     * keyed by its §6.4 normalisation - the same count assertWithinLimit()
     * makes, from one read of the Production's sends in the window
     * (recordsSince()) matched in PHP, so the list never reads per row.
     *
     * Every version used in the window must be in the keyring, as for a
     * send; when one is missing nothing can be counted correctly and every
     * address maps to null (never a count that is too low). The current
     * version is not needed to count. Reads only - nothing is written.
     *
     * @param string[] $emails
     * @return array<string, InvitationSendLimit|null>
     */
    public function sendLimitsFor(ProductionId $productionId, array $emails, DateTimeImmutable $now): array
    {
        $normalized = array_values(array_unique(array_map([InvitationEmailAddress::class, 'normalize'], $emails)));

        if ($normalized === []) {
            return [];
        }

        $records = $this->sends->recordsSince($productionId, $now->sub(new DateInterval(self::WINDOW)));
        $keyByVersion = [];

        foreach ($records as $record) {
            $version = $record->emailKeyVersion();

            if (! array_key_exists($version, $keyByVersion)) {
                $keyByVersion[$version] = $this->keyring->keyFor($version);
            }

            if ($keyByVersion[$version] === null) {
                return array_fill_keys($normalized, null);
            }
        }

        $limits = [];

        foreach ($normalized as $address) {
            $wanted = [];

            foreach ($keyByVersion as $version => $key) {
                $wanted[(string) $version] = self::emailKey($address, (string) $key);
            }

            $requestedAts = [];

            foreach ($records as $record) {
                if (hash_equals($wanted[$record->emailKeyVersion()], $record->emailKey())) {
                    $requestedAts[] = $record->requestedAt();
                }
            }

            $limits[$address] = InvitationSendLimit::fromRequestedAts($requestedAts);
        }

        return $limits;
    }

    /** §6.7: HMAC-SHA-256 of the normalised address under one key version's key, as hex. */
    private static function emailKey(string $normalizedAddress, string $key): string
    {
        return hash_hmac('sha256', $normalizedAddress, $key);
    }

    /**
     * Inside the caller's transaction, after assertWithinLimit() and after
     * the invitation was created or resend() was applied to it.
     */
    public function accept(
        ParticipantInvitation $invitation,
        string $kind,
        PersonId $requestedBy,
        string $productionName,
        DateTimeImmutable $now
    ): PendingInvitationSend {
        [$version, $emailKey] = $this->assertWithinLimit($invitation->productionId(), $invitation->email(), $now);

        $send = ParticipantInvitationSend::accept(
            $invitation->id(),
            $invitation->productionId(),
            $emailKey,
            $version,
            $kind,
            $requestedBy,
            $now
        );
        $this->sends->add($send);

        $invitation->recordSendAccepted($send->id(), $now);
        $this->invitations->save($invitation);

        if ($kind === ParticipantInvitationSend::KIND_INITIAL) {
            $this->events->invited($invitation, $send->id(), $requestedBy, $now);
        } else {
            $this->events->resent($invitation, $send->id(), $requestedBy, $now);
        }

        return new PendingInvitationSend($send->id(), $invitation->id(), $invitation->productionId(), $invitation->email(), $productionName);
    }

    /**
     * After the commit. Returns the recorded result (SENT / FAILED /
     * SUPERSEDED), or null when it could not be recorded (it then stays
     * PENDING and reads as UNKNOWN after 10 minutes, §6.2).
     */
    public function deliver(PendingInvitationSend $pending): ?string
    {
        try {
            if ($this->invitations->currentSendIdOf($pending->invitationId) !== $pending->sendId) {
                return $this->record($pending, ParticipantInvitationSend::SUPERSEDED);
            }

            try {
                $sent = $this->mailer->sendInvitationEmail($pending->email, $pending->productionName);
            } catch (Throwable $exception) {
                $sent = false;
            }

            return $this->record($pending, $sent ? ParticipantInvitationSend::SENT : ParticipantInvitationSend::FAILED);
        } catch (Throwable $exception) {
            error_log(sprintf(
                '[StageArt ParticipantInvitation] send result not recorded for invitation=%s send=%s (%s)',
                $pending->invitationId->toString(),
                $pending->sendId,
                get_class($exception)
            ));

            return null;
        }
    }

    private function record(PendingInvitationSend $pending, string $status): ?string
    {
        return $this->transactions->run(function () use ($pending, $status): ?string {
            $now = new DateTimeImmutable();

            if (! $this->sends->completeIfPending($pending->sendId, $status, $now)) {
                return null;
            }

            if ($status === ParticipantInvitationSend::FAILED) {
                $this->events->sendFailed($pending->productionId, $pending->invitationId, $pending->sendId, $now);
            }

            return $status;
        });
    }
}
