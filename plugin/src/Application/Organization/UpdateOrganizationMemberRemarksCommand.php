<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

final class UpdateOrganizationMemberRemarksCommand
{
    public string $organizationId;
    public string $personId;
    public int $requestedByWordPressUserId;
    public ?string $remarks;

    public function __construct(string $organizationId, string $personId, int $requestedByWordPressUserId, ?string $remarks)
    {
        $this->organizationId = $organizationId;
        $this->personId = $personId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->remarks = $remarks;
    }
}
