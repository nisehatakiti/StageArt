<?php

declare(strict_types=1);

namespace StageArt\Application\JoinKey;

final class DisableJoinKeyCommand
{
    public int $requestedByWordPressUserId;
    public string $joinKeyId;

    public function __construct(int $requestedByWordPressUserId, string $joinKeyId)
    {
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->joinKeyId = $joinKeyId;
    }
}
