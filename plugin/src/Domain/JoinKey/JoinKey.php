<?php

declare(strict_types=1);

namespace StageArt\Domain\JoinKey;

use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Domain\Person\PersonId;

/**
 * docs/04-DomainModel/JoinKey.md: a participation-entry trigger, deliberately
 * NOT a substitute Identity for Organization/Production ("OrganizationId
 * やProductionIdそのものを外部へ露出する代替識別子ではない") - `targetId` is
 * the real Organization/Production UUID, `code` is only ever used to
 * resolve back to it, never as a lookup key for anything else. Entering
 * a code never grants Membership/Participant status by itself - see
 * RequestOrganizationMembershipUseCase/RequestProductionParticipationUseCase,
 * which still create a REQUESTED/PENDING row requiring separate approval.
 */
final class JoinKey
{
    public const TARGET_TYPE_ORGANIZATION = 'ORGANIZATION';
    public const TARGET_TYPE_PRODUCTION = 'PRODUCTION';

    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_DISABLED = 'DISABLED';
    public const STATUS_EXPIRED = 'EXPIRED';
    public const STATUS_EXHAUSTED = 'EXHAUSTED';

    private const VALID_TARGET_TYPES = [self::TARGET_TYPE_ORGANIZATION, self::TARGET_TYPE_PRODUCTION];
    private const CODE_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    private const CODE_LENGTH = 8;

    private JoinKeyId $id;
    private string $code;
    private string $targetType;
    private string $targetId;
    private string $status;
    private PersonId $issuedByPersonId;
    private DateTimeImmutable $issuedAt;
    private ?DateTimeImmutable $expiresAt;
    private ?int $maxUses;
    private int $useCount;
    private ?DateTimeImmutable $disabledAt;

    private function __construct(
        JoinKeyId $id,
        string $code,
        string $targetType,
        string $targetId,
        string $status,
        PersonId $issuedByPersonId,
        DateTimeImmutable $issuedAt,
        ?DateTimeImmutable $expiresAt,
        ?int $maxUses,
        int $useCount,
        ?DateTimeImmutable $disabledAt
    ) {
        if (! in_array($targetType, self::VALID_TARGET_TYPES, true)) {
            throw new InvalidArgumentException("Invalid JoinKey targetType: {$targetType}");
        }

        $this->id = $id;
        $this->code = $code;
        $this->targetType = $targetType;
        $this->targetId = $targetId;
        $this->status = $status;
        $this->issuedByPersonId = $issuedByPersonId;
        $this->issuedAt = $issuedAt;
        $this->expiresAt = $expiresAt;
        $this->maxUses = $maxUses;
        $this->useCount = $useCount;
        $this->disabledAt = $disabledAt;
    }

    public static function issueForOrganization(
        string $organizationId,
        PersonId $issuedByPersonId,
        ?DateTimeImmutable $expiresAt = null,
        ?int $maxUses = null
    ): self {
        return self::issue(self::TARGET_TYPE_ORGANIZATION, $organizationId, $issuedByPersonId, $expiresAt, $maxUses);
    }

    public static function issueForProduction(
        string $productionId,
        PersonId $issuedByPersonId,
        ?DateTimeImmutable $expiresAt = null,
        ?int $maxUses = null
    ): self {
        return self::issue(self::TARGET_TYPE_PRODUCTION, $productionId, $issuedByPersonId, $expiresAt, $maxUses);
    }

    private static function issue(
        string $targetType,
        string $targetId,
        PersonId $issuedByPersonId,
        ?DateTimeImmutable $expiresAt,
        ?int $maxUses
    ): self {
        return new self(
            JoinKeyId::generate(),
            self::generateCode(),
            $targetType,
            $targetId,
            self::STATUS_ACTIVE,
            $issuedByPersonId,
            new DateTimeImmutable(),
            $expiresAt,
            $maxUses,
            0,
            null
        );
    }

    public static function reconstitute(
        JoinKeyId $id,
        string $code,
        string $targetType,
        string $targetId,
        string $status,
        PersonId $issuedByPersonId,
        DateTimeImmutable $issuedAt,
        ?DateTimeImmutable $expiresAt,
        ?int $maxUses,
        int $useCount,
        ?DateTimeImmutable $disabledAt
    ): self {
        return new self($id, $code, $targetType, $targetId, $status, $issuedByPersonId, $issuedAt, $expiresAt, $maxUses, $useCount, $disabledAt);
    }

    /** docs/04-DomainModel/JoinKey.md's "Code Format": 8 uppercase
     * alphanumeric characters, sufficiently random - not derived from
     * targetId. */
    private static function generateCode(): string
    {
        $alphabetLength = strlen(self::CODE_ALPHABET);
        $code = '';

        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= self::CODE_ALPHABET[random_int(0, $alphabetLength - 1)];
        }

        return $code;
    }

    /** Strips hyphens/whitespace and uppercases, matching JoinKey.md's
     * "入力時には大文字小文字およびハイフンを正規化できる" - `AB7K-29XZ` and
     * `ab7k29xz` both normalize to the same lookup value. */
    public static function normalizeCode(string $rawCode): string
    {
        return strtoupper(str_replace(['-', ' '], '', $rawCode));
    }

    public function id(): JoinKeyId
    {
        return $this->id;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function targetType(): string
    {
        return $this->targetType;
    }

    public function targetId(): string
    {
        return $this->targetId;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function issuedByPersonId(): PersonId
    {
        return $this->issuedByPersonId;
    }

    public function issuedAt(): DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function expiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function maxUses(): ?int
    {
        return $this->maxUses;
    }

    public function useCount(): int
    {
        return $this->useCount;
    }

    public function disabledAt(): ?DateTimeImmutable
    {
        return $this->disabledAt;
    }

    /** JoinKey.md's Lifecycle: usable only while ACTIVE, not expired, and
     * not exhausted - computed live rather than relying solely on a
     * stored `status`, since expiry is time-dependent and this Aggregate
     * is not re-saved merely because time passed. */
    public function isUsable(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        if ($this->expiresAt !== null && $this->expiresAt < new DateTimeImmutable()) {
            return false;
        }

        if ($this->maxUses !== null && $this->useCount >= $this->maxUses) {
            return false;
        }

        return true;
    }

    /** Called once a Join Key actually results in a Membership/Participant
     * request being created (not merely previewed via resolve) - see
     * JoinKey.md's own distinction between resolving/confirming a target
     * and actually using the key. Transitions to EXHAUSTED the moment
     * this use reaches `maxUses`. */
    public function recordUse(): void
    {
        if (! $this->isUsable()) {
            throw new InvalidArgumentException('This Join Key can no longer be used.');
        }

        $this->useCount++;

        if ($this->maxUses !== null && $this->useCount >= $this->maxUses) {
            $this->status = self::STATUS_EXHAUSTED;
        }
    }

    public function disable(): void
    {
        $this->status = self::STATUS_DISABLED;
        $this->disabledAt = new DateTimeImmutable();
    }
}
