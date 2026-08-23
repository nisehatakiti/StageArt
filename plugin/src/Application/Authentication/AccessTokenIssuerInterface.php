<?php

declare(strict_types=1);

namespace StageArt\Application\Authentication;

use StageArt\Domain\Person\PersonId;
use StageArt\Domain\UserAccount\UserAccountId;

/**
 * Port for issuing StageArt's own Access Token - deliberately separate
 * from Google's ID Token (never reused as the API token, per this
 * Phase's explicit requirement). The Application layer has no knowledge
 * of JWT, signing algorithms, or any token format detail; it only knows
 * "give me a token for this UserAccount/Person" and "how long is it
 * good for".
 */
interface AccessTokenIssuerInterface
{
    public function issue(UserAccountId $userAccountId, PersonId $personId): AccessTokenResult;
}
