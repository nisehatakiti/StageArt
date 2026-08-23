<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use StageArt\Domain\Account\Account;
use StageArt\Domain\Account\AccountId;
use StageArt\Domain\Account\AccountRepositoryInterface;
use StageArt\Domain\Account\AccountStatus;
use StageArt\Domain\Account\AccountType;
use StageArt\Domain\Organization\OrganizationId;
use wpdb;

final class WordPressAccountRepository implements AccountRepositoryInterface
{
    private wpdb $wpdb;
    private string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_accounts';
    }

    public function save(Account $account): void
    {
        $row = [
            'organization_id' => $account->organizationId()->toString(),
            'name' => $account->name(),
            'type' => $account->type()->toString(),
            'code' => $account->code(),
            'parent_account_id' => $account->parentAccountId() !== null ? $account->parentAccountId()->toString() : null,
            'status' => $account->status()->toString(),
            'updated_at' => $account->updatedAt()->format('Y-m-d H:i:s'),
        ];

        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT id FROM {$this->table} WHERE id = %s", $account->id()->toString())
        );

        if ($existing) {
            $this->wpdb->update($this->table, $row, ['id' => $account->id()->toString()]);
        } else {
            $row['id'] = $account->id()->toString();
            $row['created_at'] = $account->createdAt()->format('Y-m-d H:i:s');

            $this->wpdb->insert($this->table, $row);
        }
    }

    public function findById(AccountId $id): ?Account
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %s", $id->toString()),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function findByIds(array $ids): array
    {
        if (count($ids) === 0) {
            return [];
        }

        $idStrings = array_map(static fn (AccountId $id): string => $id->toString(), $ids);
        $placeholders = implode(',', array_fill(0, count($idStrings), '%s'));

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id IN ({$placeholders})", ...$idStrings),
            ARRAY_A
        );

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    public function findByOrganizationId(OrganizationId $organizationId): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE organization_id = %s ORDER BY code ASC, name ASC",
                $organizationId->toString()
            ),
            ARRAY_A
        );

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    private function hydrate(array $row): Account
    {
        return Account::reconstitute(
            AccountId::fromString($row['id']),
            OrganizationId::fromString($row['organization_id']),
            $row['name'],
            AccountType::fromString($row['type']),
            $row['code'],
            $row['parent_account_id'] !== null ? AccountId::fromString($row['parent_account_id']) : null,
            AccountStatus::fromString($row['status']),
            new DateTimeImmutable($row['created_at']),
            new DateTimeImmutable($row['updated_at'])
        );
    }
}
