<?php

declare(strict_types=1);

namespace StageArt\Application\Person;

final class GetPersonByIdQuery
{
    public string $personId;
    public int $requestedByWordPressUserId;

    public function __construct(string $personId, int $requestedByWordPressUserId)
    {
        $this->personId = $personId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
    }
}
