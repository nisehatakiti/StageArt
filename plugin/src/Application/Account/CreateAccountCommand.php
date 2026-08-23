<?php

declare(strict_types=1);

namespace StageArt\Application\Account;

final class CreateAccountCommand
{
    public int $requestedByWordPressUserId;
    public string $organizationId;
    public string $name;
    public string $type;
    public ?string $code;
    public ?string $parentAccountId;

    public function __construct(
        int $requestedByWordPressUserId,
        string $organizationId,
        string $name,
        string $type,
        ?string $code = null,
        ?string $parentAccountId = null
    ) {
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->organizationId = $organizationId;
        $this->name = $name;
        $this->type = $type;
        $this->code = $code;
        $this->parentAccountId = $parentAccountId;
    }
}
