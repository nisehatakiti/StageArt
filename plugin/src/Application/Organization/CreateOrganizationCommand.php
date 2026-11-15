<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

final class CreateOrganizationCommand
{
    public int $requestedByWordPressUserId;
    public string $name;
    public string $slug;
    public ?string $type;
    public ?string $description;
    public bool $accountingEnabled;
    /** Yen. Null/0 means "no opening balance for this Account". */
    public ?int $openingCashBalance;
    public ?int $openingBankBalance;

    public function __construct(
        int $requestedByWordPressUserId,
        string $name,
        string $slug,
        ?string $type = null,
        ?string $description = null,
        bool $accountingEnabled = false,
        ?int $openingCashBalance = null,
        ?int $openingBankBalance = null
    ) {
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->name = $name;
        $this->slug = $slug;
        $this->type = $type;
        $this->description = $description;
        $this->accountingEnabled = $accountingEnabled;
        $this->openingCashBalance = $openingCashBalance;
        $this->openingBankBalance = $openingBankBalance;
    }
}
