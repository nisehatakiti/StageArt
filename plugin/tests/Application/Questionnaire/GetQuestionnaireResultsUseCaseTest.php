<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Questionnaire;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionOrganizationResolver;
use StageArt\Application\Questionnaire\GetQuestionnaireResultsQuery;
use StageArt\Application\Questionnaire\GetQuestionnaireResultsUseCase;
use StageArt\Core\Adapter\CoreAuthorizationAdapter;
use StageArt\Core\Adapter\CoreIdentityAdapter;
use StageArt\Core\Adapter\CoreProductionContextAdapter;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\Project\Project;
use StageArt\Domain\Questionnaire\Question;
use StageArt\Domain\Questionnaire\QuestionChoice;
use StageArt\Domain\Questionnaire\Questionnaire;
use StageArt\Domain\Questionnaire\QuestionnaireResponse;
use StageArt\Domain\Questionnaire\QuestionType;
use StageArt\Domain\Questionnaire\ResponseAnswer;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionDelegateRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;
use StageArt\Tests\Support\InMemoryProjectRepository;
use StageArt\Tests\Support\InMemoryQuestionRepository;
use StageArt\Tests\Support\InMemoryQuestionnaireRepository;
use StageArt\Tests\Support\InMemoryQuestionnaireResponseRepository;

final class GetQuestionnaireResultsUseCaseTest extends TestCase
{
    public function test_aggregates_single_choice_rating_and_free_text(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $people = new InMemoryPersonRepository();
        $memberships = new InMemoryMembershipRepository();
        $productions = new InMemoryProductionRepository();
        $delegates = new InMemoryProductionDelegateRepository();
        $participants = new InMemoryParticipantRepository();
        $projects = new InMemoryProjectRepository();
        $questionnaires = new InMemoryQuestionnaireRepository();
        $questions = new InMemoryQuestionRepository();
        $responses = new InMemoryQuestionnaireResponseRepository();

        $organizationAuthorization = new OrganizationAuthorizationService($people, $memberships);
        $productionAuthorization = new ProductionAuthorizationService($organizationAuthorization, $delegates, $participants);
        $productionContext = new CoreProductionContextAdapter($productions, new ProductionOrganizationResolver($projects), $organizations);
        $identity = new CoreIdentityAdapter($people);
        $authorization = new CoreAuthorizationAdapter($productionAuthorization, $productions, $people);

        $getResults = new GetQuestionnaireResultsUseCase($questionnaires, $questions, $responses, $productionContext, $identity, $authorization);

        $organization = Organization::create(new OrganizationName('Theatre Co'));
        $organizations->save($organization);
        $primaryManager = Person::create(1);
        $people->save($primaryManager);
        $memberships->save(Membership::createOwnerMembership($organization->id(), $primaryManager->id()));
        $project = Project::create($organization->id(), 'Season');
        $projects->save($project);
        $production = Production::create($project->id(), new ProductionName('Show'), $primaryManager->id());
        $productions->save($production);

        $questionnaire = Questionnaire::create($production->id(), 'アンケート', null, null);
        $questionnaire->publish(null);
        $questionnaires->save($questionnaire);

        $good = QuestionChoice::create('良い', 0);
        $bad = QuestionChoice::create('悪い', 1);
        $choiceQuestion = Question::create($questionnaire->id(), 'いかがでしたか？', QuestionType::fromString(QuestionType::SINGLE_CHOICE), true, 0, [$good, $bad]);
        $questions->save($choiceQuestion);

        $ratingQuestion = Question::create($questionnaire->id(), '満足度は？', QuestionType::fromString(QuestionType::RATING_5), true, 1, []);
        $questions->save($ratingQuestion);

        $freeTextQuestion = Question::create($questionnaire->id(), 'ご意見は？', QuestionType::fromString(QuestionType::FREE_TEXT), false, 2, []);
        $questions->save($freeTextQuestion);

        $responses->save(QuestionnaireResponse::submit($questionnaire->id(), [
            ResponseAnswer::create($choiceQuestion->id()->toString(), $good->id()),
            ResponseAnswer::create($ratingQuestion->id()->toString(), 5),
            ResponseAnswer::create($freeTextQuestion->id()->toString(), 'とても良かったです'),
        ]));
        $responses->save(QuestionnaireResponse::submit($questionnaire->id(), [
            ResponseAnswer::create($choiceQuestion->id()->toString(), $good->id()),
            ResponseAnswer::create($ratingQuestion->id()->toString(), 3),
        ]));
        $responses->save(QuestionnaireResponse::submit($questionnaire->id(), [
            ResponseAnswer::create($choiceQuestion->id()->toString(), $bad->id()),
            ResponseAnswer::create($ratingQuestion->id()->toString(), 1),
        ]));

        $result = $getResults->execute(new GetQuestionnaireResultsQuery($production->id()->toString(), 1));

        $this->assertSame(3, $result->totalResponses);

        $choiceResult = $result->questions[0];
        $this->assertSame(2, $choiceResult->choiceCounts[0]['count']);
        $this->assertSame(1, $choiceResult->choiceCounts[1]['count']);

        $ratingResult = $result->questions[1];
        $this->assertSame(3.0, $ratingResult->ratingAverage);
        $this->assertSame(1, $ratingResult->ratingCounts[5]);
        $this->assertSame(1, $ratingResult->ratingCounts[3]);
        $this->assertSame(1, $ratingResult->ratingCounts[1]);

        $freeTextResult = $result->questions[2];
        $this->assertSame(['とても良かったです'], $freeTextResult->freeTextAnswers);
    }
}
