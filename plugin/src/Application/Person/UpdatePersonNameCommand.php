<?php

declare(strict_types=1);

namespace StageArt\Application\Person;

final class UpdatePersonNameCommand
{
    public int $requestedByWordPressUserId;
    public string $familyName;
    public string $givenName;

    public function __construct(int $requestedByWordPressUserId, string $familyName, string $givenName)
    {
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->familyName = $familyName;
        $this->givenName = $givenName;
    }
}
