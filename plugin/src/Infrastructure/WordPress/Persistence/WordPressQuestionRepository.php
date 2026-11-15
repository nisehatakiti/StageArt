<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use RuntimeException;
use StageArt\Domain\Questionnaire\Question;
use StageArt\Domain\Questionnaire\QuestionChoice;
use StageArt\Domain\Questionnaire\QuestionId;
use StageArt\Domain\Questionnaire\QuestionnaireId;
use StageArt\Domain\Questionnaire\QuestionRepositoryInterface;
use StageArt\Domain\Questionnaire\QuestionType;
use wpdb;

/**
 * `choices_json` holds a Question's QuestionChoice list as a JSON array -
 * the same "structured sub-data as a JSON-encoded TEXT column" shape
 * UpdateQuotaAndTicketBackSettingsUseCase already uses for Production's
 * own `ticket_back_rules`, just encoded/decoded here in the Repository
 * (this Aggregate's persistence boundary) instead of the Application
 * layer, since a Question's own choice list is Domain data, not a
 * Ticket-Back-style settings blob.
 */
final class WordPressQuestionRepository implements QuestionRepositoryInterface
{
    private wpdb $wpdb;
    private string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_questionnaire_questions';
    }

    public function save(Question $question): void
    {
        $row = [
            'questionnaire_id' => $question->questionnaireId()->toString(),
            'text' => $question->text(),
            'type' => $question->type()->toString(),
            'required' => $question->required() ? 1 : 0,
            'display_order' => $question->displayOrder(),
            'choices_json' => json_encode(array_map(static fn (QuestionChoice $choice) => $choice->toArray(), $question->choices())),
            'updated_at' => $question->updatedAt()->format('Y-m-d H:i:s'),
        ];

        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT id FROM {$this->table} WHERE id = %s", $question->id()->toString())
        );

        if ($existing) {
            $result = $this->wpdb->update($this->table, $row, ['id' => $question->id()->toString()]);

            if ($result === false) {
                throw new RuntimeException('Failed to update Question.');
            }

            return;
        }

        $row['id'] = $question->id()->toString();
        $row['created_at'] = $question->createdAt()->format('Y-m-d H:i:s');

        $result = $this->wpdb->insert($this->table, $row);

        if ($result === false) {
            throw new RuntimeException('Failed to insert Question.');
        }
    }

    public function findById(QuestionId $id): ?Question
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %s", $id->toString()),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function delete(QuestionId $id): void
    {
        $this->wpdb->delete($this->table, ['id' => $id->toString()]);
    }

    public function findByQuestionnaireId(QuestionnaireId $questionnaireId): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE questionnaire_id = %s ORDER BY display_order ASC",
                $questionnaireId->toString()
            ),
            ARRAY_A
        );

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    private function hydrate(array $row): Question
    {
        $choicesData = json_decode((string) $row['choices_json'], true) ?: [];

        $choices = array_map(
            static fn (array $choice): QuestionChoice => QuestionChoice::reconstitute(
                $choice['id'],
                $choice['label'],
                (int) $choice['display_order']
            ),
            $choicesData
        );

        return Question::reconstitute(
            QuestionId::fromString($row['id']),
            QuestionnaireId::fromString($row['questionnaire_id']),
            $row['text'],
            QuestionType::fromString($row['type']),
            (bool) $row['required'],
            (int) $row['display_order'],
            $choices,
            new DateTimeImmutable($row['created_at']),
            new DateTimeImmutable($row['updated_at'])
        );
    }
}
