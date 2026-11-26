<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\ProductionDelegate\ProductionDelegate;
use StageArt\Domain\ProductionDelegate\ProductionDelegateId;
use StageArt\Domain\ProductionDelegate\ProductionDelegateRepositoryInterface;
use StageArt\Domain\Role\RoleKey;
use wpdb;

/**
 * PR #7 R14-B stage 3 (design doc §4.6 / §5.4 / §7.4): the delegate rows
 * are deactivated together with a Participant leaving (or (re)joining)
 * the Production, so this Repository is one R14-B changes:
 *
 * - save() and delete() detect a failed write (WordPressCheckedWrites:
 *   UNIQUE violation as UniqueConstraintViolationException, anything else
 *   as PersistenceException) instead of ignoring it, and a failed
 *   existence read never turns into an INSERT.
 * - findByProductionAndPersonForUpdate() locks one Person's rows in one
 *   Production (SELECT ... FOR UPDATE, last in the §5.4 lock order
 *   Production -> invitation -> Participant -> delegate) and throws on a
 *   failed read.
 *
 * Date-time formatting and the other reads are unchanged.
 */
final class WordPressProductionDelegateRepository implements ProductionDelegateRepositoryInterface
{
    private wpdb $wpdb;
    private string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_production_delegates';
    }

    public function save(ProductionDelegate $delegate): void
    {
        $row = [
            'production_id' => $delegate->productionId()->toString(),
            'person_id' => $delegate->personId()->toString(),
            'role' => $delegate->role()->toString(),
            'status' => $delegate->status(),
            'updated_by' => $delegate->updatedBy()->toString(),
            'updated_at' => $delegate->updatedAt()->format('Y-m-d H:i:s'),
        ];

        $id = $delegate->id()->toString();

        if (WordPressCheckedWrites::existingId($this->wpdb, $this->table, $id) !== null) {
            WordPressCheckedWrites::update($this->wpdb, $this->table, $row, ['id' => $id]);
            return;
        }

        $row['id'] = $id;
        $row['created_by'] = $delegate->createdBy()->toString();
        $row['created_at'] = $delegate->createdAt()->format('Y-m-d H:i:s');

        WordPressCheckedWrites::insert($this->wpdb, $this->table, $row);
    }

    public function findByProductionAndPersonForUpdate(ProductionId $productionId, PersonId $personId): array
    {
        $this->wpdb->last_error = '';
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE production_id = %s AND person_id = %s ORDER BY id FOR UPDATE",
                $productionId->toString(),
                $personId->toString()
            ),
            ARRAY_A
        );
        WordPressCheckedWrites::assertRead($this->wpdb, $this->table);

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    public function findById(ProductionDelegateId $id): ?ProductionDelegate
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %s", $id->toString()),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function findByProductionId(ProductionId $productionId): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE production_id = %s", $productionId->toString()),
            ARRAY_A
        );

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    public function findByPersonId(PersonId $personId): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE person_id = %s", $personId->toString()),
            ARRAY_A
        );

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    public function findByProductionAndPersonAndRole(
        ProductionId $productionId,
        PersonId $personId,
        RoleKey $role
    ): ?ProductionDelegate {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE production_id = %s AND person_id = %s AND role = %s",
                $productionId->toString(),
                $personId->toString(),
                $role->toString()
            ),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function delete(ProductionDelegateId $id): void
    {
        WordPressCheckedWrites::delete($this->wpdb, $this->table, ['id' => $id->toString()]);
    }

    private function hydrate(array $row): ProductionDelegate
    {
        return ProductionDelegate::reconstitute(
            ProductionDelegateId::fromString($row['id']),
            ProductionId::fromString($row['production_id']),
            PersonId::fromString($row['person_id']),
            RoleKey::fromString($row['role']),
            $row['status'],
            PersonId::fromString($row['created_by']),
            new DateTimeImmutable($row['created_at']),
            PersonId::fromString($row['updated_by']),
            new DateTimeImmutable($row['updated_at'])
        );
    }
}
