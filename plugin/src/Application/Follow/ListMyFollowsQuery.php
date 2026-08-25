<?php

declare(strict_types=1);

namespace StageArt\Application\Follow;

final class ListMyFollowsQuery
{
    public int $requestedByWordPressUserId;

    public function __construct(int $requestedByWordPressUserId)
    {
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
    }
}
