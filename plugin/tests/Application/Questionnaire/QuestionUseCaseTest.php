<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Questionnaire;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionOrganizationResolver;
use StageArt\Application\Questionnaire\AddQuestionCommand;
use StageArt\Application\Questionnaire\AddQuestionUseCase;
use StageArt\Application\Questionnaire\CreateQuestionnaireCommand;
use StageArt\Application\Questionnaire\CreateQuestionnaireUseCase;
use StageArt\Application\Questionnaire\DeleteQuestionCommand;
use StageArt\Application\Questionnaire\DeleteQuestionUseCase;
use StageArt\Application\Questionnaire\QuestionEditLockedException;
use StageArt\Application\Questionnaire\QuestionEditPolicy;
use StageArt\Application\Questionnaire\QuestionnairePublicUrlResolver;
use StageArt\Application\Questionnaire\ReorderQuestionsCommand;
use StageArt\Application\Questionnaire\ReorderQuestionsUseCase;
use StageArt\Application\Questionnaire\UpdateQuestionCommand;
use StageArt\Application\Questionnaire\UpdateQuestionUseCase;
use StageArt\Core\Adapter\CoreAuthorizationAdapter;
use StageArt\Core\Adapter\CoreIdentityAdapter;
use StageArt\Core\Adapter\CoreProductionContextAdapter;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\Questionnaire\QuestionnaireId;
use StageArt\Domain\Questionnaire\QuestionnaireResponse;
use StageArt\Domain\Questionnaire\ResponseAnswer;
use StageArt\Domain\Project\Project;
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

final class QuestionUseCaseTest extends TestCase
{
    private InMemoryQuestionnaireRepository $questionnaires;
    private InMemoryQuestionRepository $questions;
    private InMemoryQuestionnaireResponseRepository $responses;
    private Production $production;
    private Person $primaryManager;

    private CreateQuestionnaireUseCase $createQuestionnaire;
    private AddQuestionUseCase $addQuestion;
    private UpdateQuestionUseCase $updateQuestion;
    private DeleteQuestionUseCase $deleteQuestion;
    private ReorderQuestionsUseCase $reorderQuestions;

    protected function setUp(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $people = new InMemoryPersonRepository();
        $memberships = new InMemoryMembershipRepository();
        $productions = new InMemoryProductionRepository();
        $delegates = new InMemoryProductionDelegateRepository();
        $participants = new InMemoryParticipantRepository();
        $projects = new InMemoryProjectRepository();
        $this->questionnaires = new InMemoryQuestionnaireRepository();
        $this->questions = new InMemoryQuestionRepository();
        $this->responses = new InMemoryQuestionnaireResponseRepository();

        $organizationAuthorization = new OrganizationAuthorizationService($people, $memberships);
        $productionAuthorization = new ProductionAuthorizationService($organizationAuthorization, $delegates, $participants);
        $productionContext = new CoreProductionContextAdapter($productions, new ProductionOrganizationResolver($projects), $organizations);
        $identity = new CoreIdentityAdapter($people);
        $authorization = new CoreAuthorizationAdapter($productionAuthorization, $productions, $people);
        $publicUrlResolver = new QuestionnairePublicUrlResolver($productionContext, 'https://dummy.stageart.top');
        $editPolicy = new QuestionEditPolicy($this->responses);

        $this->createQuestionnaire = new CreateQuestionnaireUseCase($this->questionnaires, $productionContext, $identity, $authorization, $publicUrlResolver);
        $this->addQuestion = new AddQuestionUseCase($this->questionnaires, $this->questions, $productionContext, $identity, $authorization);
        $this->updateQuestion = new UpdateQuestionUseCase($this->questionnaires, $this->questions, $productionContext, $identity, $authorization, $editPolicy);
        $this->deleteQuestion = new DeleteQuestionUseCase($this->questionnaires, $this->questions, $productionContext, $identity, $authorization, $editPolicy);
        $this->reorderQuestions = new ReorderQuestionsUseCase($this->questionnaires, $this->questions, $productionContext, $identity, $authorization);

        $organization = Organization::create(new OrganizationName('Theatre Co'));
        $organizations->save($organization);

        $this->primaryManager = Person::create(1);
        $people->save($this->primaryManager);
        $memberships->save(Membership::createOwnerMembership($organization->id(), $this->primaryManager->id()));

        $project = Project::create($organization->id(), 'Season');
        $projects->save($project);

        $this->production = Production::create($project->id(), new ProductionName('Show'), $this->primaryManager->id());
        $productions->save($this->production);

        $this->createQuestionnaire->execute(new CreateQuestionnaireCommand($this->production->id()->toString(), 1, 'アンケート', null));
    }

    public function test_add_single_choice_question_with_choices(): void
    {
        $result = $this->addQuestion->execute(new AddQuestionCommand(
            $this->production->id()->toString(),
            1,
            '公演はいかがでしたか？',
            'SINGLE_CHOICE',
            true,
            ['とても良い', '良い', '普通']
        ));

        $this->assertCount(3, $result->choices);
        $this->assertSame(0, $result->displayOrder);
    }

    public function test_second_question_gets_next_display_order(): void
    {
        $this->addQuestion->execute(new AddQuestionCommand($this->production->id()->toString(), 1, '質問1', 'YES_NO', true, []));
        $second = $this->addQuestion->execute(new AddQuestionCommand($this->production->id()->toString(), 1, '質問2', 'FREE_TEXT', false, []));

        $this->assertSame(1, $second->displayOrder);
    }

    public function test_reorder_questions_changes_display_order(): void
    {
        $first = $this->addQuestion->execute(new AddQuestionCommand($this->production->id()->toString(), 1, '質問1', 'YES_NO', true, []));
        $second = $this->addQuestion->execute(new AddQuestionCommand($this->production->id()->toString(), 1, '質問2', 'FREE_TEXT', false, []));

        $reordered = $this->reorderQuestions->execute(new ReorderQuestionsCommand(
            $this->production->id()->toString(),
            1,
            [$second->id, $first->id]
        ));

        $this->assertSame($second->id, $reordered[0]->id);
        $this->assertSame(0, $reordered[0]->displayOrder);
        $this->assertSame($first->id, $reordered[1]->id);
        $this->assertSame(1, $reordered[1]->displayOrder);
    }

    public function test_delete_question_without_responses_succeeds(): void
    {
        $question = $this->addQuestion->execute(new AddQuestionCommand($this->production->id()->toString(), 1, '質問1', 'YES_NO', true, []));

        $this->deleteQuestion->execute(new DeleteQuestionCommand($this->production->id()->toString(), $question->id, 1));

        $this->assertCount(0, $this->questions->findByQuestionnaireId(QuestionnaireId::fromString($question->questionnaireId)));
    }

    public function test_delete_question_with_existing_response_is_rejected(): void
    {
        $question = $this->addQuestion->execute(new AddQuestionCommand($this->production->id()->toString(), 1, '質問1', 'YES_NO', true, []));

        $this->responses->save(QuestionnaireResponse::submit(
            QuestionnaireId::fromString($question->questionnaireId),
            [ResponseAnswer::create($question->id, 'YES')]
        ));

        $this->expectException(QuestionEditLockedException::class);
        $this->deleteQuestion->execute(new DeleteQuestionCommand($this->production->id()->toString(), $question->id, 1));
    }

    public function test_removing_an_answered_choice_is_rejected(): void
    {
        $question = $this->addQuestion->execute(new AddQuestionCommand(
            $this->production->id()->toString(),
            1,
            'いかがでしたか？',
            'SINGLE_CHOICE',
            true,
            ['良い', '普通']
        ));
        $answeredChoiceId = $question->choices[0]['id'];

        $this->responses->save(QuestionnaireResponse::submit(
            QuestionnaireId::fromString($question->questionnaireId),
            [ResponseAnswer::create($question->id, $answeredChoiceId)]
        ));

        $this->expectException(QuestionEditLockedException::class);
        $this->updateQuestion->execute(new UpdateQuestionCommand(
            $this->production->id()->toString(),
            $question->id,
            1,
            'いかがでしたか？',
            true,
            [['id' => null, 'label' => '新しい選択肢のみ']]
        ));
    }

    public function test_appending_a_new_choice_after_answers_exist_is_allowed(): void
    {
        $question = $this->addQuestion->execute(new AddQuestionCommand(
            $this->production->id()->toString(),
            1,
            'いかがでしたか？',
            'SINGLE_CHOICE',
            true,
            ['良い']
        ));
        $existingChoiceId = $question->choices[0]['id'];

        $this->responses->save(QuestionnaireResponse::submit(
            QuestionnaireId::fromString($question->questionnaireId),
            [ResponseAnswer::create($question->id, $existingChoiceId)]
        ));

        $updated = $this->updateQuestion->execute(new UpdateQuestionCommand(
            $this->production->id()->toString(),
            $question->id,
            1,
            'いかがでしたか？',
            true,
            [
                ['id' => $existingChoiceId, 'label' => '良い'],
                ['id' => null, 'label' => '悪い'],
            ]
        ));

        $this->assertCount(2, $updated->choices);
    }
}
