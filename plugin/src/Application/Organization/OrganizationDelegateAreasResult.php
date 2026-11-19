<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

use StageArt\Domain\Membership\Membership;

/** One delegate's 管理領域 - only the Person id and the areas, nothing else about them. */
final class OrganizationDelegateAreasResult
{
    public string $personId;
    /** @var string[] */
    public array $areas;

    /** @param string[] $areas */
    private function __construct(string $personId, array $areas)
    {
        $this->personId = $personId;
        $this->areas = $areas;
    }

    public static function fromDomain(Membership $membership): self
    {
        return new self($membership->personId()->toString(), $membership->delegateAreas());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'person_id' => $this->personId,
            'areas' => $this->areas,
        ];
    }
}
