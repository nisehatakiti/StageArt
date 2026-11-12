<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Questionnaire;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionOrganizationResolver;
use StageArt\Application\Questionnaire\CloseQuestionnaireCommand;
use StageArt\Application\Questionnaire\CloseQuestionnaireUseCase;
use StageArt\Application\Questionnaire\CreateQuestionnaireCommand;
use StageArt\Application\Questionnaire\CreateQuestionnaireUseCase;
use StageArt\Application\Questionnaire\GetQuestionnaireQuery;
use StageArt\Application\Questionnaire\GetQuestionnaireUseCase;
use StageArt\Application\Questionnaire\PublishQuestionnaireCommand;
use StageArt\Application\Questionnaire\PublishQuestionnaireUseCase;
use StageArt\Application\Questionnaire\QuestionnaireAccessDeniedException;
use StageArt\Application\Questionnaire\QuestionnaireAlreadyExistsException;
use StageArt\Application\Questionnaire\QuestionnairePublicUrlResolver;
use StageArt\Application\Questionnaire\UpdateQuestionnaireCommand;
use StageArt\Application\Questionnaire\UpdateQuestionnaireUseCase;
use StageArt\Core\Adapter\CoreAuthorizationAdapter;
use StageArt\Core\Adapter\CoreIdentityAdapter;
use StageArt\Core\Adapter\CoreProductionContextAdapter;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Organization\OrganizationSlug;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\Production\ProductionSlug;
use StageArt\Domain\ProductionDelegate\ProductionDelegate;
use StageArt\Domain\Project\Project;
use StageArt\Domain\Role\RoleKey;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionDelegateRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;
use StageArt\Tests\Support\InMemoryProjectRepository;
use StageArt\Tests\Support\InMemoryQuestionRepository;
use StageArt\Tests\Support\InMemoryQuestionnaireRepository;

final class QuestionnaireUseCaseTest extends TestCase
{
    private InMemoryOrganizationRepository $organizations;
    private InMemoryPersonRepository $people;
    private InMemoryMembershipRepository $memberships;
    private InMemoryProductionRepository $productions;
    private InMemoryProductionDelegateRepository $delegates;
    private InMemoryParticipantRepository $participants;
    private InMemoryProjectRepository $projects;
    private InMemoryQuestionnaireRepository $questionnaires;
    private InMemoryQuestionRepository $questions;

    private CreateQuestionnaireUseCase $createQuestionnaire;
    private GetQuestionnaireUseCase $getQuestionnaire;
    private UpdateQuestionnaireUseCase $updateQuestionnaire;
    private PublishQuestionnaireUseCase $publishQuestionnaire;
    private CloseQuestionnaireUseCase $closeQuestionnaire;

    protected function setUp(): void
    {
        $this->organizations = new InMemoryOrganizationRepository();
        $this->people = new InMemoryPersonRepository();
        $this->memberships = new InMemoryMembershipRepository();
        $this->productions = new InMemoryProductionRepository();
        $this->delegates = new InMemoryProductionDelegateRepository();
        $this->participants = new InMemoryParticipantRepository();
        $this->projects = new InMemoryProjectRepository();
        $this->questionnaires = new InMemoryQuestionnaireRepository();
        $this->questions = new InMemoryQuestionRepository();

        $organizationAuthorization = new OrganizationAuthorizationService($this->people, $this->memberships);
        $productionAuthorization = new ProductionAuthorizationService(
            $organizationAuthorization,
            $this->delegates,
            $this->participants
        );
        $productionContext = new CoreProductionContextAdapter(
            $this->productions,
            new ProductionOrganizationResolver($this->projects),
            $this->organizations
        );
        $identity = new CoreIdentityAdapter($this->people);
        $authorization = new CoreAuthorizationAdapter($productionAuthorization, $this->productions, $this->people);
        $publicUrlResolver = new QuestionnairePublicUrlResolver($productionContext, 'https://dummy.stageart.top');

        $this->createQuestionnaire = new CreateQuestionnaireUseCase($this->questionnaires, $productionContext, $identity, $authorization, $publicUrlResolver);
        $this->getQuestionnaire = new GetQuestionnaireUseCase($this->questionnaires, $this->questions, $productionContext, $identity, $authorization, $publicUrlResolver);
        $this->updateQuestionnaire = new UpdateQuestionnaireUseCase($this->questionnaires, $this->questions, $productionContext, $identity, $authorization, $publicUrlResolver);
        $this->publishQuestionnaire = new PublishQuestionnaireUseCase($this->questionnaires, $this->questions, $productionContext, $identity, $authorization, $publicUrlResolver);
        $this->closeQuestionnaire = new CloseQuestionnaireUseCase($this->questionnaires, $this->questions, $productionContext, $identity, $authorization, $publicUrlResolver);
    }

    private function givenProductionWithPrimaryManager(int $primaryManagerWordPressUserId): Production
    {
        $organization = Organization::create(new OrganizationName('Theatre Co'));
        $organization->changeSlug(new OrganizationSlug('theatre-co-' . $primaryManagerWordPressUserId));
        $this->organizations->save($organization);

        $primaryManager = Person::create($primaryManagerWordPressUserId);
        $this->people->save($primaryManager);
        $this->memberships->save(Membership::createOwnerMembership($organization->id(), $primaryManager->id()));

        $project = Project::create($organization->id(), 'Season');
        $this->projects->save($project);

        $production = Production::create($project->id(), new ProductionName('Show'), $primaryManager->id());
        $production->changeSlug(new ProductionSlug('show-' . $primaryManagerWordPressUserId));
        $this->productions->save($production);

        return $production;
    }

    public function test_primary_manager_can_create_questionnaire_in_draft(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $result = $this->createQuestionnaire->execute(new CreateQuestionnaireCommand(
            $production->id()->toString(),
            1,
            'アンケート',
            '説明'
        ));

        $this->assertSame('DRAFT', $result->status);
        $this->assertStringEndsWith('/questionnaire', $result->publicUrl);
    }

    public function test_cannot_create_second_questionnaire_for_same_production(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $this->createQuestionnaire->execute(new CreateQuestionnaireCommand($production->id()->toString(), 1, 'アンケート', null));

        $this->expectException(QuestionnaireAlreadyExistsException::class);
        $this->createQuestionnaire->execute(new CreateQuestionnaireCommand($production->id()->toString(), 1, '別のアンケート', null));
    }

    public function test_plain_participant_cannot_create_questionnaire(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $stranger = Person::create(2);
        $this->people->save($stranger);

        $this->expectException(QuestionnaireAccessDeniedException::class);
        $this->createQuestionnaire->execute(new CreateQuestionnaireCommand($production->id()->toString(), 2, 'アンケート', null));
    }

    public function test_questionnaire_manager_delegate_can_create_questionnaire(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $delegatePerson = Person::create(5);
        $this->people->save($delegatePerson);
        $this->delegates->save(ProductionDelegate::create(
            $production->id(),
            $delegatePerson->id(),
            RoleKey::questionnaireManager(),
            $production->primaryManagerPersonId()
        ));

        $result = $this->createQuestionnaire->execute(new CreateQuestionnaireCommand($production->id()->toString(), 5, 'アンケート', null));

        $this->assertSame($production->id()->toString(), $result->productionId);
    }

    public function test_publish_then_close_lifecycle(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->createQuestionnaire->execute(new CreateQuestionnaireCommand($production->id()->toString(), 1, 'アンケート', null));

        $published = $this->publishQuestionnaire->execute(new PublishQuestionnaireCommand($production->id()->toString(), 1));
        $this->assertSame('PUBLISHED', $published->status);

        $closed = $this->closeQuestionnaire->execute(new CloseQuestionnaireCommand($production->id()->toString(), 1));
        $this->assertSame('CLOSED', $closed->status);
    }

    public function test_update_details_changes_title_and_response_end_at(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->createQuestionnaire->execute(new CreateQuestionnaireCommand($production->id()->toString(), 1, 'アンケート', null));

        $updated = $this->updateQuestionnaire->execute(new UpdateQuestionnaireCommand(
            $production->id()->toString(),
            1,
            '更新後タイトル',
            '更新後の説明',
            '2026-12-31 23:59:59'
        ));

        $this->assertSame('更新後タイトル', $updated->title);
        $this->assertNotNull($updated->responseEndAt);
    }

    public function test_get_questionnaire_denies_non_manager(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->createQuestionnaire->execute(new CreateQuestionnaireCommand($production->id()->toString(), 1, 'アンケート', null));

        $stranger = Person::create(99);
        $this->people->save($stranger);

        $this->expectException(QuestionnaireAccessDeniedException::class);
        $this->getQuestionnaire->execute(new GetQuestionnaireQuery($production->id()->toString(), 99));
    }
}
