<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\Questionnaire;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Domain\Questionnaire\Question;
use StageArt\Domain\Questionnaire\QuestionChoice;
use StageArt\Domain\Questionnaire\QuestionnaireId;
use StageArt\Domain\Questionnaire\QuestionType;

final class QuestionTest extends TestCase
{
    public function test_single_choice_requires_at_least_one_choice(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Question::create(
            QuestionnaireId::generate(),
            '公演はいかがでしたか？',
            QuestionType::fromString(QuestionType::SINGLE_CHOICE),
            true,
            0,
            []
        );
    }

    public function test_free_text_needs_no_choices(): void
    {
        $question = Question::create(
            QuestionnaireId::generate(),
            '感想を教えてください',
            QuestionType::fromString(QuestionType::FREE_TEXT),
            false,
            0,
            []
        );

        $this->assertSame([], $question->choices());
    }

    public function test_create_rejects_empty_text(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Question::create(QuestionnaireId::generate(), '  ', QuestionType::fromString(QuestionType::YES_NO), true, 0, []);
    }

    public function test_type_has_no_mutator(): void
    {
        $this->assertFalse(method_exists(Question::class, 'changeType'));
    }

    public function test_update_content_can_append_new_choice(): void
    {
        $choiceA = QuestionChoice::create('良い', 0);
        $question = Question::create(
            QuestionnaireId::generate(),
            'いかがでしたか？',
            QuestionType::fromString(QuestionType::SINGLE_CHOICE),
            true,
            0,
            [$choiceA]
        );

        $choiceB = QuestionChoice::create('普通', 1);
        $question->updateContent('いかがでしたか？', true, [$choiceA, $choiceB]);

        $this->assertCount(2, $question->choices());
    }

    public function test_find_choice_returns_null_for_unknown_id(): void
    {
        $question = Question::create(
            QuestionnaireId::generate(),
            'いかがでしたか？',
            QuestionType::fromString(QuestionType::YES_NO),
            true,
            0,
            []
        );

        $this->assertNull($question->findChoice('unknown'));
    }
}
