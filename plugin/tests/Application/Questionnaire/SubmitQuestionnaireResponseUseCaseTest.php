<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Questionnaire;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Questionnaire\GetPublicQuestionnaireQuery;
use StageArt\Application\Questionnaire\GetPublicQuestionnaireUseCase;
use StageArt\Application\Questionnaire\QuestionnaireNotAcceptingResponsesException;
use StageArt\Application\Questionnaire\QuestionnaireNotFoundException;
use StageArt\Application\Questionnaire\SubmitQuestionnaireResponseCommand;
use StageArt\Application\Questionnaire\SubmitQuestionnaireResponseUseCase;
use StageArt\Core\Adapter\CoreProductionContextAdapter;
use StageArt\Application\Production\ProductionOrganizationResolver;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\Production\ProductionSlug;
use StageArt\Domain\Project\Project;
use StageArt\Domain\Questionnaire\Question;
use StageArt\Domain\Questionnaire\QuestionChoice;
use StageArt\Domain\Questionnaire\Questionnaire;
use StageArt\Domain\Questionnaire\QuestionType;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;
use StageArt\Tests\Support\InMemoryProjectRepository;
use StageArt\Tests\Support\InMemoryQuestionRepository;
use StageArt\Tests\Support\InMemoryQuestionnaireRepository;
use StageArt\Tests\Support\InMemoryQuestionnaireResponseRepository;

final class SubmitQuestionnaireResponseUseCaseTest extends TestCase
{
    private InMemoryQuestionnaireRepository $questionnaires;
    private InMemoryQuestionRepository $questions;
    private InMemoryQuestionnaireResponseRepository $responses;
    private SubmitQuestionnaireResponseUseCase $submit;
    private GetPublicQuestionnaireUseCase $getPublic;
    private Questionnaire $questionnaire;
    private Question $ratingQuestion;
    private Question $requiredFreeTextQuestion;

    protected function setUp(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $productions = new InMemoryProductionRepository();
        $projects = new InMemoryProjectRepository();
        $this->questionnaires = new InMemoryQuestionnaireRepository();
        $this->questions = new InMemoryQuestionRepository();
        $this->responses = new InMemoryQuestionnaireResponseRepository();

        $productionContext = new CoreProductionContextAdapter($productions, new ProductionOrganizationResolver($projects), $organizations);

        $this->submit = new SubmitQuestionnaireResponseUseCase($this->questionnaires, $this->questions, $this->responses);
        $this->getPublic = new GetPublicQuestionnaireUseCase($this->questionnaires, $this->questions, $productionContext);

        $organization = Organization::create(new OrganizationName('Theatre Co'));
        $organizations->save($organization);

        $primaryManager = Person::create(1);
        $project = Project::create($organization->id(), 'Season');
        $projects->save($project);

        $production = Production::create($project->id(), new ProductionName('Show'), $primaryManager->id());
        $production->changeSlug(new ProductionSlug('show-slug'));
        $productions->save($production);

        $this->questionnaire = Questionnaire::create($production->id(), 'アンケート', null, null);
        $this->questionnaire->publish(null);
        $this->questionnaires->save($this->questionnaire);
        $this->questionnaires->registerSlug($this->questionnaire->id(), 'show-slug');

        $this->ratingQuestion = Question::create($this->questionnaire->id(), '満足度は？', QuestionType::fromString(QuestionType::RATING_5), true, 0, []);
        $this->questions->save($this->ratingQuestion);

        $this->requiredFreeTextQuestion = Question::create($this->questionnaire->id(), 'ご意見を教えてください', QuestionType::fromString(QuestionType::FREE_TEXT), false, 1, []);
        $this->questions->save($this->requiredFreeTextQuestion);
    }

    public function test_valid_submission_is_accepted(): void
    {
        $this->submit->execute(new SubmitQuestionnaireResponseCommand('show-slug', [
            ['question_id' => $this->ratingQuestion->id()->toString(), 'value' => 5],
        ]));

        $this->assertSame(1, $this->responses->countByQuestionnaireId($this->questionnaire->id()));
    }

    public function test_missing_required_answer_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->submit->execute(new SubmitQuestionnaireResponseCommand('show-slug', []));
    }

    public function test_out_of_range_rating_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->submit->execute(new SubmitQuestionnaireResponseCommand('show-slug', [
            ['question_id' => $this->ratingQuestion->id()->toString(), 'value' => 9],
        ]));
    }

    public function test_optional_question_can_be_left_blank(): void
    {
        $this->submit->execute(new SubmitQuestionnaireResponseCommand('show-slug', [
            ['question_id' => $this->ratingQuestion->id()->toString(), 'value' => 3],
        ]));

        $responses = $this->responses->findByQuestionnaireId($this->questionnaire->id());
        $this->assertCount(1, $responses[0]->answers());
    }

    public function test_draft_questionnaire_rejects_submission(): void
    {
        $draftQuestionnaire = Questionnaire::create($this->questionnaire->productionId(), '下書き', null, null);
        $this->questionnaires->save($draftQuestionnaire);
        $this->questionnaires->registerSlug($draftQuestionnaire->id(), 'draft-slug');

        $this->expectException(QuestionnaireNotAcceptingResponsesException::class);
        $this->submit->execute(new SubmitQuestionnaireResponseCommand('draft-slug', []));
    }

    public function test_unknown_slug_rejects_submission_as_not_found(): void
    {
        $this->expectException(QuestionnaireNotFoundException::class);
        $this->submit->execute(new SubmitQuestionnaireResponseCommand('no-such-slug', []));
    }

    public function test_closed_questionnaire_rejects_submission(): void
    {
        $this->questionnaire->close(null);
        $this->questionnaires->save($this->questionnaire);

        $this->expectException(QuestionnaireNotAcceptingResponsesException::class);
        $this->submit->execute(new SubmitQuestionnaireResponseCommand('show-slug', [
            ['question_id' => $this->ratingQuestion->id()->toString(), 'value' => 5],
        ]));
    }

    public function test_public_get_hides_draft_and_shows_published(): void
    {
        $result = $this->getPublic->execute(new GetPublicQuestionnaireQuery('show-slug'));

        $this->assertTrue($result->acceptingResponses);
        $this->assertCount(2, $result->questions);
    }

    public function test_single_choice_answer_must_reference_a_real_choice(): void
    {
        $choiceQuestion = Question::create(
            $this->questionnaire->id(),
            'いかがでしたか？',
            QuestionType::fromString(QuestionType::SINGLE_CHOICE),
            true,
            2,
            [QuestionChoice::create('良い', 0)]
        );
        $this->questions->save($choiceQuestion);

        $this->expectException(InvalidArgumentException::class);
        $this->submit->execute(new SubmitQuestionnaireResponseCommand('show-slug', [
            ['question_id' => $this->ratingQuestion->id()->toString(), 'value' => 5],
            ['question_id' => $choiceQuestion->id()->toString(), 'value' => 'not-a-real-choice-id'],
        ]));
    }
}
