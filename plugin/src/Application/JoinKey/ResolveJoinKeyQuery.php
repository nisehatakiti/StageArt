<?php

declare(strict_types=1);

namespace StageArt\Application\JoinKey;

final class ResolveJoinKeyQuery
{
    public string $rawCode;

    public function __construct(string $rawCode)
    {
        $this->rawCode = $rawCode;
    }
}
