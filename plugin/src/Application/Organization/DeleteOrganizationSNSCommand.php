<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

final class DeleteOrganizationSNSCommand
{
    public string $id;
    public int $requestedByWordPressUserId;

    public function __construct(string $id, int $requestedByWordPressUserId)
    {
        $this->id = $id;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
    }
}
