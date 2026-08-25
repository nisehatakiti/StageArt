<?php

declare(strict_types=1);

namespace StageArt\Application\Production;

final class SearchProductionsQuery
{
    public string $query;

    public function __construct(string $query)
    {
        $this->query = $query;
    }
}
