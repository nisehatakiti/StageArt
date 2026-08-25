<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

final class SearchOrganizationsQuery
{
    public string $query;

    public function __construct(string $query)
    {
        $this->query = $query;
    }
}
