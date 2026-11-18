<?php

declare(strict_types=1);

namespace StageArt\Domain\ParticipantInvitation;

use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;

/**
 * R14-B §6.2 / §11.3 (stage 4): one send attempt of an invitation e-mail
 * (stageart_participant_invitation_sends). A row is added, PENDING, when
 * the create / resend API accepts the send - that is when the send is
 * counted; the result (SENT / FAILED / SUPERSEDED) is recorded later on
 * this same row and never changes the count.
 *
 * The address itself is not stored: `emailKey` is the HMAC-SHA-256 of the
 * normalised address under the key version `emailKeyVersion` (§6.7), used
 * only to count the sends to one address within 30 days.
 */
final class ParticipantInvitationSend
{
    public const KIND_INITIAL = 'INITIAL';
    public const KIND_RESEND = 'RESEND';

    public const PENDING = 'PENDING';
    public const SENT = 'SENT';
    public const FAILED = 'FAILED';
    public const SUPERSEDED = 'SUPERSEDED';

    /** §6.2: derived, never stored - a PENDING attempt whose result was not recorded within UNKNOWN_AFTER. */
    public const UNKNOWN = 'UNKNOWN';
    /** §6.2【推奨】: 10 minutes. */
    public const UNKNOWN_AFTER = 'PT10M';

    private const KINDS = [self::KIND_INITIAL, self::KIND_RESEND];
    private const RESULTS = [self::SENT, self::FAILED, self::SUPERSEDED];

    private string $id;
    private ParticipantInvitationId $invitationId;
    private ProductionId $productionId;
    private string $emailKey;
    private string $emailKeyVersion;
    private string $kind;
    private PersonId $requestedByPersonId;
    private DateTimeImmutable $requestedAt;

    private function __construct(
        string $id,
        ParticipantInvitationId $invitationId,
        ProductionId $productionId,
        string $emailKey,
        string $emailKeyVersion,
        string $kind,
        PersonId $requestedByPersonId,
        DateTimeImmutable $requestedAt
    ) {
        if (! in_array($kind, self::KINDS, true)) {
            throw new InvalidArgumentException("Invalid send kind: {$kind}");
        }

        if (preg_match('/^[0-9a-f]{64}$/', $emailKey) !== 1) {
            throw new InvalidArgumentException('emailKey must be a hex HMAC-SHA-256.');
        }

        if ($emailKeyVersion === '' || strlen($emailKeyVersion) > 20) {
            throw new InvalidArgumentException('emailKeyVersion must be 1-20 characters.');
        }

        $this->id = $id;
        $this->invitationId = $invitationId;
        $this->productionId = $productionId;
        $this->emailKey = $emailKey;
        $this->emailKeyVersion = $emailKeyVersion;
        $this->kind = $kind;
        $this->requestedByPersonId = $requestedByPersonId;
        $this->requestedAt = $requestedAt;
    }

    public static function accept(
        ParticipantInvitationId $invitationId,
        ProductionId $productionId,
        string $emailKey,
        string $emailKeyVersion,
        string $kind,
        PersonId $requestedByPersonId,
        DateTimeImmutable $requestedAt
    ): self {
        return new self(
            ParticipantInvitationId::generate()->toString(),
            $invitationId,
            $productionId,
            $emailKey,
            $emailKeyVersion,
            $kind,
            $requestedByPersonId,
            $requestedAt
        );
    }

    public static function isResult(string $status): bool
    {
        return in_array($status, self::RESULTS, true);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function invitationId(): ParticipantInvitationId
    {
        return $this->invitationId;
    }

    public function productionId(): ProductionId
    {
        return $this->productionId;
    }

    public function emailKey(): string
    {
        return $this->emailKey;
    }

    public function emailKeyVersion(): string
    {
        return $this->emailKeyVersion;
    }

    public function kind(): string
    {
        return $this->kind;
    }

    public function requestedByPersonId(): PersonId
    {
        return $this->requestedByPersonId;
    }

    public function requestedAt(): DateTimeImmutable
    {
        return $this->requestedAt;
    }
}
