<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use RuntimeException;
use StageArt\Domain\Questionnaire\QuestionId;
use StageArt\Domain\Questionnaire\QuestionnaireId;
use StageArt\Domain\Questionnaire\QuestionnaireResponse;
use StageArt\Domain\Questionnaire\QuestionnaireResponseRepositoryInterface;
use StageArt\Domain\Questionnaire\ResponseAnswer;
use StageArt\Domain\Questionnaire\ResponseId;
use wpdb;

/**
 * §10/§50: this table's own columns are the enforcement surface for the
 * anonymity requirement - `id`, `questionnaire_id`, `answers_json`,
 * `submitted_at`, and nothing else. No person_id/reservation_id/email/
 * ip/user_agent column exists here, and none may ever be added without
 * also changing QuestionnaireResponse::class itself (see that class's own
 * docblock).
 */
final class WordPressQuestionnaireResponseRepository implements QuestionnaireResponseRepositoryInterface
{
    private wpdb $wpdb;
    private string $table;
    private string $questionsTable;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'stageart_questionnaire_responses';
        $this->questionsTable = $wpdb->prefix . 'stageart_questionnaire_questions';
    }

    public function save(QuestionnaireResponse $response): void
    {
        $answers = array_map(
            static fn (ResponseAnswer $answer): array => [
                'question_id' => $answer->questionId(),
                'value' => $answer->value(),
            ],
            $response->answers()
        );

        $result = $this->wpdb->insert($this->table, [
            'id' => $response->id()->toString(),
            'questionnaire_id' => $response->questionnaireId()->toString(),
            'answers_json' => json_encode($answers),
            'submitted_at' => $response->submittedAt()->format('Y-m-d H:i:s'),
        ]);

        if ($result === false) {
            throw new RuntimeException('Failed to insert QuestionnaireResponse.');
        }
    }

    public function findByQuestionnaireId(QuestionnaireId $questionnaireId): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE questionnaire_id = %s ORDER BY submitted_at ASC",
                $questionnaireId->toString()
            ),
            ARRAY_A
        );

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    public function countByQuestionnaireId(QuestionnaireId $questionnaireId): int
    {
        return (int) $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT COUNT(*) FROM {$this->table} WHERE questionnaire_id = %s", $questionnaireId->toString())
        );
    }

    public function existsAnswerForQuestion(QuestionId $questionId): bool
    {
        $questionnaireId = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT questionnaire_id FROM {$this->questionsTable} WHERE id = %s", $questionId->toString())
        );

        if (! $questionnaireId) {
            return false;
        }

        $rows = $this->wpdb->get_col(
            $this->wpdb->prepare("SELECT answers_json FROM {$this->table} WHERE questionnaire_id = %s", $questionnaireId)
        );

        foreach ($rows ?: [] as $answersJson) {
            $answers = json_decode((string) $answersJson, true) ?: [];
            foreach ($answers as $answer) {
                if (($answer['question_id'] ?? null) === $questionId->toString()) {
                    return true;
                }
            }
        }

        return false;
    }

    private function hydrate(array $row): QuestionnaireResponse
    {
        $answersData = json_decode((string) $row['answers_json'], true) ?: [];

        $answers = array_map(
            static fn (array $answer): ResponseAnswer => ResponseAnswer::create($answer['question_id'], $answer['value']),
            $answersData
        );

        return QuestionnaireResponse::reconstitute(
            ResponseId::fromString($row['id']),
            QuestionnaireId::fromString($row['questionnaire_id']),
            $answers,
            new DateTimeImmutable($row['submitted_at'])
        );
    }
}
