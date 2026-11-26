<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

final class ListMyParticipatingProductionsQuery
{
    public int $requestedByWordPressUserId;

    public function __construct(int $requestedByWordPressUserId)
    {
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
    }
}
