<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Application\Authentication\WordPressUserProvisionerInterface;

final class FakeWordPressUserProvisioner implements WordPressUserProvisionerInterface
{
    private int $nextId;

    public function __construct(int $startingAt = 100)
    {
        $this->nextId = $startingAt;
    }

    public function provision(?string $googleEmail): int
    {
        return $this->nextId++;
    }
}
