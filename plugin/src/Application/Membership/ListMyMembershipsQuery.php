<?php

declare(strict_types=1);

namespace StageArt\Application\Membership;

final class ListMyMembershipsQuery
{
    public int $requestedByWordPressUserId;

    public function __construct(int $requestedByWordPressUserId)
    {
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
    }
}
