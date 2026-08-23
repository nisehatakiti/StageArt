<?php

declare(strict_types=1);

namespace StageArt\Application\Authentication;

final class LinkGoogleIdentityCommand
{
    public int $requestedByWordPressUserId;
    public string $idToken;

    public function __construct(int $requestedByWordPressUserId, string $idToken)
    {
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->idToken = $idToken;
    }
}
