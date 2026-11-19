<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

final class UpdateOrganizationDelegateAreasCommand
{
    public string $organizationId;
    public int $requestedByWordPressUserId;
    public string $targetPersonId;
    /** @var mixed[] the requested DelegateArea values, validated by the Use Case */
    public array $areas;

    /** @param mixed[] $areas */
    public function __construct(string $organizationId, int $requestedByWordPressUserId, string $targetPersonId, array $areas)
    {
        $this->organizationId = $organizationId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->targetPersonId = $targetPersonId;
        $this->areas = $areas;
    }
}
