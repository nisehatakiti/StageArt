<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\Questionnaire;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Questionnaire\Questionnaire;
use StageArt\Domain\Questionnaire\QuestionnaireStatus;

final class QuestionnaireTest extends TestCase
{
    public function test_create_starts_in_draft(): void
    {
        $questionnaire = Questionnaire::create(ProductionId::generate(), 'アンケート', '説明', null);

        $this->assertSame(QuestionnaireStatus::DRAFT, $questionnaire->status()->toString());
        $this->assertNull($questionnaire->responseEndAt());
    }

    public function test_create_rejects_empty_title(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Questionnaire::create(ProductionId::generate(), '   ', null, null);
    }

    public function test_publish_moves_draft_to_published(): void
    {
        $questionnaire = Questionnaire::create(ProductionId::generate(), 'アンケート', null, null);

        $questionnaire->publish(null);

        $this->assertSame(QuestionnaireStatus::PUBLISHED, $questionnaire->status()->toString());
    }

    public function test_publish_rejects_already_published(): void
    {
        $questionnaire = Questionnaire::create(ProductionId::generate(), 'アンケート', null, null);
        $questionnaire->publish(null);

        $this->expectException(InvalidArgumentException::class);
        $questionnaire->publish(null);
    }

    public function test_close_requires_published(): void
    {
        $questionnaire = Questionnaire::create(ProductionId::generate(), 'アンケート', null, null);

        $this->expectException(InvalidArgumentException::class);
        $questionnaire->close(null);
    }

    public function test_close_moves_published_to_closed(): void
    {
        $questionnaire = Questionnaire::create(ProductionId::generate(), 'アンケート', null, null);
        $questionnaire->publish(null);

        $questionnaire->close(null);

        $this->assertSame(QuestionnaireStatus::CLOSED, $questionnaire->status()->toString());
    }

    public function test_update_details_rejected_once_closed(): void
    {
        $questionnaire = Questionnaire::create(ProductionId::generate(), 'アンケート', null, null);
        $questionnaire->publish(null);
        $questionnaire->close(null);

        $this->expectException(InvalidArgumentException::class);
        $questionnaire->updateDetails('新タイトル', null, null, null);
    }

    public function test_draft_never_accepts_responses(): void
    {
        $questionnaire = Questionnaire::create(ProductionId::generate(), 'アンケート', null, null);

        $this->assertFalse($questionnaire->isAcceptingResponses(new DateTimeImmutable()));
    }

    public function test_published_without_deadline_accepts_responses(): void
    {
        $questionnaire = Questionnaire::create(ProductionId::generate(), 'アンケート', null, null);
        $questionnaire->publish(null);

        $this->assertTrue($questionnaire->isAcceptingResponses(new DateTimeImmutable()));
    }

    public function test_published_past_deadline_rejects_responses(): void
    {
        $questionnaire = Questionnaire::create(ProductionId::generate(), 'アンケート', null, null);
        $questionnaire->publish(null);
        $questionnaire->updateDetails('アンケート', null, new DateTimeImmutable('2020-01-01'), null);

        $this->assertFalse($questionnaire->isAcceptingResponses(new DateTimeImmutable('2020-01-02')));
    }

    public function test_closed_never_accepts_responses_even_before_deadline(): void
    {
        $questionnaire = Questionnaire::create(ProductionId::generate(), 'アンケート', null, null);
        $questionnaire->publish(null);
        $questionnaire->close(null);

        $this->assertFalse($questionnaire->isAcceptingResponses(new DateTimeImmutable()));
    }
}
