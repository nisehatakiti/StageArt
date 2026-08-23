<?php

declare(strict_types=1);

namespace StageArt\Application\UserAccount;

use StageArt\Domain\UserAccount\UserAccount;

final class UserAccountResult
{
    public string $id;
    public string $personId;
    public string $status;
    public string $createdAt;
    public string $updatedAt;

    private function __construct(
        string $id,
        string $personId,
        string $status,
        string $createdAt,
        string $updatedAt
    ) {
        $this->id = $id;
        $this->personId = $personId;
        $this->status = $status;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public static function fromDomain(UserAccount $userAccount): self
    {
        return new self(
            $userAccount->id()->toString(),
            $userAccount->personId()->toString(),
            $userAccount->status()->toString(),
            $userAccount->createdAt()->format(DATE_ATOM),
            $userAccount->updatedAt()->format(DATE_ATOM)
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'person_id' => $this->personId,
            'status' => $this->status,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
