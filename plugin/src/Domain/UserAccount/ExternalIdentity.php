<?php

declare(strict_types=1);

namespace StageArt\Domain\UserAccount;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Links an external Authentication Provider identity (e.g. Google) to a
 * UserAccount. Identified by (provider, providerUserId) - the provider's
 * own stable subject identifier (e.g. Google's `sub`), never the
 * provider's email address, per UserAccount.md's "# External Identity"
 * and this project's explicit decision to not use email as an identity
 * key. Linking/unlinking flows themselves are not implemented in this
 * slice; this is the data shape only.
 */
final class ExternalIdentity
{
    private ExternalIdentityId $id;
    private UserAccountId $userAccountId;
    private string $provider;
    private string $providerUserId;
    private DateTimeImmutable $linkedAt;

    private function __construct(
        ExternalIdentityId $id,
        UserAccountId $userAccountId,
        string $provider,
        string $providerUserId,
        DateTimeImmutable $linkedAt
    ) {
        $this->id = $id;
        $this->userAccountId = $userAccountId;
        $this->provider = $provider;
        $this->providerUserId = $providerUserId;
        $this->linkedAt = $linkedAt;
    }

    public static function create(UserAccountId $userAccountId, string $provider, string $providerUserId): self
    {
        return new self(
            ExternalIdentityId::generate(),
            $userAccountId,
            self::validateNonEmpty($provider, 'provider'),
            self::validateNonEmpty($providerUserId, 'providerUserId'),
            new DateTimeImmutable()
        );
    }

    public static function reconstitute(
        ExternalIdentityId $id,
        UserAccountId $userAccountId,
        string $provider,
        string $providerUserId,
        DateTimeImmutable $linkedAt
    ): self {
        return new self($id, $userAccountId, $provider, $providerUserId, $linkedAt);
    }

    private static function validateNonEmpty(string $value, string $field): string
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException("ExternalIdentity {$field} must not be empty.");
        }

        return $value;
    }

    public function id(): ExternalIdentityId
    {
        return $this->id;
    }

    public function userAccountId(): UserAccountId
    {
        return $this->userAccountId;
    }

    public function provider(): string
    {
        return $this->provider;
    }

    public function providerUserId(): string
    {
        return $this->providerUserId;
    }

    public function linkedAt(): DateTimeImmutable
    {
        return $this->linkedAt;
    }
}
