<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Domain\Organization\OrganizationExternalLink;
use StageArt\Domain\Organization\OrganizationExternalLinkId;
use StageArt\Domain\Organization\OrganizationExternalLinkRepositoryInterface;
use StageArt\Domain\Organization\OrganizationId;

final class InMemoryOrganizationExternalLinkRepository implements OrganizationExternalLinkRepositoryInterface
{
    /** @var array<string, OrganizationExternalLink> */
    private array $records = [];

    public function save(OrganizationExternalLink $link): void
    {
        $this->records[$link->id()->toString()] = $link;
    }

    public function findById(OrganizationExternalLinkId $id): ?OrganizationExternalLink
    {
        return $this->records[$id->toString()] ?? null;
    }

    public function findByOrganizationId(OrganizationId $organizationId): array
    {
        $matches = array_values(array_filter(
            $this->records,
            static fn (OrganizationExternalLink $link): bool => $link->organizationId()->equals($organizationId)
        ));

        usort($matches, static fn (OrganizationExternalLink $a, OrganizationExternalLink $b): int => $a->displayOrder() <=> $b->displayOrder());

        return $matches;
    }

    public function delete(OrganizationExternalLinkId $id): void
    {
        unset($this->records[$id->toString()]);
    }
}
