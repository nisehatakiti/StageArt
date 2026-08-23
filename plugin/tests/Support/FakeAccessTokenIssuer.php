<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Application\Authentication\AccessTokenIssuerInterface;
use StageArt\Application\Authentication\AccessTokenResult;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\UserAccount\UserAccountId;

final class FakeAccessTokenIssuer implements AccessTokenIssuerInterface
{
    private int $issuedCount = 0;

    public function issue(UserAccountId $userAccountId, PersonId $personId): AccessTokenResult
    {
        $this->issuedCount++;

        return new AccessTokenResult("fake-access-token-{$this->issuedCount}-{$userAccountId->toString()}", 3600);
    }

    public function issuedCount(): int
    {
        return $this->issuedCount;
    }
}
