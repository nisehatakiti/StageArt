<?php

declare(strict_types=1);

namespace StageArt\Application\Authentication;

final class AuthenticateWithGoogleCommand
{
    public string $idToken;

    public function __construct(string $idToken)
    {
        $this->idToken = $idToken;
    }
}
