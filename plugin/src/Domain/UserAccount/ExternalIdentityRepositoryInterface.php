<?php

declare(strict_types=1);

namespace StageArt\Domain\UserAccount;

interface ExternalIdentityRepositoryInterface
{
    public function save(ExternalIdentity $identity): void;

    /**
     * @return ExternalIdentity[]
     */
    public function findByUserAccountId(UserAccountId $userAccountId): array;

    public function findByProviderAndProviderUserId(string $provider, string $providerUserId): ?ExternalIdentity;
}
