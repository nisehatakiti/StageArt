<?php

declare(strict_types=1);

namespace StageArt\Domain\Questionnaire;

use DateTimeImmutable;

/**
 * アンケート実装指示書 §9/§10/§12/§13: the anonymous answer Aggregate. This
 * class's own constructor parameter list IS the enforcement mechanism for
 * §10's ban list - there is no PersonId, ReservationId, BookerEmail,
 * AccountId, ExternalIdentityId, IssuedTicketId, IP, User-Agent, Device id
 * or any other respondent-identifying field anywhere on this Entity, and
 * none can be added without editing this file (searchable, reviewable in
 * one place - see this feature's final "匿名性レビュー" §50/§66).
 *
 * No `submittedBy`/`createdBy` field either, unlike almost every other
 * Entity in this codebase - that omission is deliberate, not an oversight.
 *
 * §13: V1 never deduplicates by respondent (no Cookie/Device
 * id/IP is stored to do so with), so nothing here prevents or even
 * detects multiple Responses "from the same person" - every submission is
 * simply a new row.
 */
final class QuestionnaireResponse
{
    private ResponseId $id;
    private QuestionnaireId $questionnaireId;
    /** @var ResponseAnswer[] */
    private array $answers;
    private DateTimeImmutable $submittedAt;

    /**
     * @param ResponseAnswer[] $answers
     */
    private function __construct(ResponseId $id, QuestionnaireId $questionnaireId, array $answers, DateTimeImmutable $submittedAt)
    {
        $this->id = $id;
        $this->questionnaireId = $questionnaireId;
        $this->answers = array_values($answers);
        $this->submittedAt = $submittedAt;
    }

    /**
     * @param ResponseAnswer[] $answers
     */
    public static function submit(QuestionnaireId $questionnaireId, array $answers): self
    {
        return new self(ResponseId::generate(), $questionnaireId, $answers, new DateTimeImmutable());
    }

    /**
     * @param ResponseAnswer[] $answers
     */
    public static function reconstitute(ResponseId $id, QuestionnaireId $questionnaireId, array $answers, DateTimeImmutable $submittedAt): self
    {
        return new self($id, $questionnaireId, $answers, $submittedAt);
    }

    public function id(): ResponseId
    {
        return $this->id;
    }

    public function questionnaireId(): QuestionnaireId
    {
        return $this->questionnaireId;
    }

    /**
     * @return ResponseAnswer[]
     */
    public function answers(): array
    {
        return $this->answers;
    }

    public function submittedAt(): DateTimeImmutable
    {
        return $this->submittedAt;
    }
}
