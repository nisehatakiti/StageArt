<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Domain\UserAccount\ExternalIdentity;
use StageArt\Domain\UserAccount\ExternalIdentityRepositoryInterface;
use StageArt\Domain\UserAccount\UserAccountId;

final class InMemoryExternalIdentityRepository implements ExternalIdentityRepositoryInterface
{
    /** @var array<string, ExternalIdentity> */
    private array $identities = [];

    public function save(ExternalIdentity $identity): void
    {
        $this->identities[$identity->id()->toString()] = $identity;
    }

    public function findByUserAccountId(UserAccountId $userAccountId): array
    {
        return array_values(array_filter(
            $this->identities,
            static fn (ExternalIdentity $identity): bool => $identity->userAccountId()->equals($userAccountId)
        ));
    }

    public function findByProviderAndProviderUserId(string $provider, string $providerUserId): ?ExternalIdentity
    {
        foreach ($this->identities as $identity) {
            if ($identity->provider() === $provider && $identity->providerUserId() === $providerUserId) {
                return $identity;
            }
        }

        return null;
    }
}
