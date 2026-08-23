<?php

declare(strict_types=1);

namespace StageArt\Application\Account;

use StageArt\Domain\Account\Account;

final class AccountResult
{
    public string $id;
    public string $organizationId;
    public string $name;
    public string $type;
    public ?string $code;
    public ?string $parentAccountId;
    public string $status;
    public string $createdAt;
    public string $updatedAt;

    private function __construct(
        string $id,
        string $organizationId,
        string $name,
        string $type,
        ?string $code,
        ?string $parentAccountId,
        string $status,
        string $createdAt,
        string $updatedAt
    ) {
        $this->id = $id;
        $this->organizationId = $organizationId;
        $this->name = $name;
        $this->type = $type;
        $this->code = $code;
        $this->parentAccountId = $parentAccountId;
        $this->status = $status;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public static function fromDomain(Account $account): self
    {
        return new self(
            $account->id()->toString(),
            $account->organizationId()->toString(),
            $account->name(),
            $account->type()->toString(),
            $account->code(),
            $account->parentAccountId() !== null ? $account->parentAccountId()->toString() : null,
            $account->status()->toString(),
            $account->createdAt()->format(DATE_ATOM),
            $account->updatedAt()->format(DATE_ATOM)
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organizationId,
            'name' => $this->name,
            'type' => $this->type,
            'code' => $this->code,
            'parent_account_id' => $this->parentAccountId,
            'status' => $this->status,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
