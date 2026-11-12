<?php

declare(strict_types=1);

namespace StageArt\Application\OrganizationInvitation;

final class CreateOrganizationInvitationCommand
{
    public string $organizationId;
    public int $requestedByWordPressUserId;
    public string $name;
    public string $email;
    public ?string $remarks;

    public function __construct(
        string $organizationId,
        int $requestedByWordPressUserId,
        string $name,
        string $email,
        ?string $remarks = null
    ) {
        $this->organizationId = $organizationId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->name = $name;
        $this->email = $email;
        $this->remarks = $remarks;
    }
}
