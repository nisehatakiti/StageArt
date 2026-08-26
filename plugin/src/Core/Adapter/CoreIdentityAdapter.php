<?php

declare(strict_types=1);

namespace StageArt\Core\Adapter;

use StageArt\Core\Contract\IdentityContract;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Person\PersonRepositoryInterface;

final class CoreIdentityAdapter implements IdentityContract
{
    private PersonRepositoryInterface $people;

    public function __construct(PersonRepositoryInterface $people)
    {
        $this->people = $people;
    }

    public function resolveCurrentPersonId(int $wordPressUserId): ?PersonId
    {
        $person = $this->people->findByWordPressUserId($wordPressUserId);

        return $person?->id();
    }
}
