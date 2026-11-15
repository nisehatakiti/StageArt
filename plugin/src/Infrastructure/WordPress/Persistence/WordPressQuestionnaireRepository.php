<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use RuntimeException;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Questionnaire\Questionnaire;
use StageArt\Domain\Questionnaire\QuestionnaireId;
use StageArt\Domain\Questionnaire\QuestionnaireRepositoryInterface;
use StageArt\Domain\Questionnaire\QuestionnaireStatus;
use wpdb;

final class WordPressQuestionnaireRepository implements QuestionnaireRepositoryInterface
{
    private wpdb $wpdb;
    private string $table;
    private string $productionsTable;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_questionnaires';
        $this->productionsTable = $wpdb->prefix . 'stageart_productions';
    }

    public function save(Questionnaire $questionnaire): void
    {
        $row = [
            'production_id' => $questionnaire->productionId()->toString(),
            'title' => $questionnaire->title(),
            'description' => $questionnaire->description(),
            'status' => $questionnaire->status()->toString(),
            'response_end_at' => $questionnaire->responseEndAt()?->format('Y-m-d H:i:s'),
            'updated_at' => $questionnaire->updatedAt()->format('Y-m-d H:i:s'),
            'updated_by' => $questionnaire->updatedBy()?->toString(),
        ];

        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT id FROM {$this->table} WHERE id = %s", $questionnaire->id()->toString())
        );

        if ($existing) {
            $result = $this->wpdb->update($this->table, $row, ['id' => $questionnaire->id()->toString()]);

            if ($result === false) {
                throw new RuntimeException('Failed to update Questionnaire.');
            }

            return;
        }

        $row['id'] = $questionnaire->id()->toString();
        $row['created_at'] = $questionnaire->createdAt()->format('Y-m-d H:i:s');
        $row['created_by'] = $questionnaire->createdBy()?->toString();

        $result = $this->wpdb->insert($this->table, $row);

        if ($result === false) {
            throw new RuntimeException('Failed to insert Questionnaire.');
        }
    }

    public function findById(QuestionnaireId $id): ?Questionnaire
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %s", $id->toString()),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function findByProductionId(ProductionId $productionId): ?Questionnaire
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE production_id = %s", $productionId->toString()),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function findByProductionSlug(string $productionSlug): ?Questionnaire
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT q.* FROM {$this->table} q
                 INNER JOIN {$this->productionsTable} p ON p.id = q.production_id
                 WHERE p.slug = %s",
                $productionSlug
            ),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    private function hydrate(array $row): Questionnaire
    {
        return Questionnaire::reconstitute(
            QuestionnaireId::fromString($row['id']),
            ProductionId::fromString($row['production_id']),
            $row['title'],
            $row['description'],
            QuestionnaireStatus::fromString($row['status']),
            $row['response_end_at'] !== null ? new DateTimeImmutable($row['response_end_at']) : null,
            new DateTimeImmutable($row['created_at']),
            new DateTimeImmutable($row['updated_at']),
            $row['created_by'] !== null ? PersonId::fromString($row['created_by']) : null,
            $row['updated_by'] !== null ? PersonId::fromString($row['updated_by']) : null
        );
    }
}
