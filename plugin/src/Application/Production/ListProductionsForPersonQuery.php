<?php

declare(strict_types=1);

namespace StageArt\Application\Production;

final class ListProductionsForPersonQuery
{
    public int $requestedByWordPressUserId;

    public function __construct(int $requestedByWordPressUserId)
    {
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
    }
}
