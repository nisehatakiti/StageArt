<?php

declare(strict_types=1);

namespace StageArt\Questionnaire;

use StageArt\Application\Questionnaire\AddQuestionUseCase;
use StageArt\Application\Questionnaire\CloseQuestionnaireUseCase;
use StageArt\Application\Questionnaire\CreateQuestionnaireUseCase;
use StageArt\Application\Questionnaire\DeleteQuestionUseCase;
use StageArt\Application\Questionnaire\GetPublicQuestionnaireUseCase;
use StageArt\Application\Questionnaire\GetQuestionnaireResultsUseCase;
use StageArt\Application\Questionnaire\GetQuestionnaireUseCase;
use StageArt\Application\Questionnaire\PublishQuestionnaireUseCase;
use StageArt\Application\Questionnaire\QuestionEditPolicy;
use StageArt\Application\Questionnaire\QuestionnaireMailerInterface;
use StageArt\Application\Questionnaire\QuestionnairePublicUrlResolver;
use StageArt\Application\Questionnaire\ReorderQuestionsUseCase;
use StageArt\Application\Questionnaire\SendQuestionnaireInvitesForPerformanceUseCase;
use StageArt\Application\Questionnaire\SubmitQuestionnaireResponseUseCase;
use StageArt\Application\Questionnaire\UpdateQuestionUseCase;
use StageArt\Application\Questionnaire\UpdateQuestionnaireUseCase;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Performance\PerformanceRepositoryInterface;
use StageArt\Domain\Questionnaire\QuestionnaireInviteRecordRepositoryInterface;
use StageArt\Domain\Questionnaire\QuestionnaireRepositoryInterface;
use StageArt\Domain\Questionnaire\QuestionnaireResponseRepositoryInterface;
use StageArt\Domain\Questionnaire\QuestionRepositoryInterface;
use StageArt\Domain\Reservation\ReservationRepositoryInterface;
use StageArt\Presentation\Rest\QuestionnaireRestController;

/**
 * StageArt Core/Module Architecture: the Questionnaire Module's entire own
 * wiring, mirroring PerformanceModuleBootstrap's exact precedent -
 * `Presentation\Plugin::boot()` is the only caller, and every constructor
 * argument below is either a Core Contract or one of this Module's own
 * `Domain\Questionnaire\*RepositoryInterface`s, never a concrete
 * `Infrastructure\WordPress\*` class.
 */
final class QuestionnaireModuleBootstrap
{
    /** @var array<int, object> */
    private array $restControllers;
    private QuestionnairePerformanceFinishedListener $performanceFinishedListener;

    public function __construct(
        QuestionnaireRepositoryInterface $questionnaires,
        QuestionRepositoryInterface $questions,
        QuestionnaireResponseRepositoryInterface $responses,
        QuestionnaireInviteRecordRepositoryInterface $inviteRecords,
        PerformanceRepositoryInterface $performances,
        ReservationRepositoryInterface $reservations,
        ProductionContextContract $productionContext,
        IdentityContract $identity,
        AuthorizationContract $authorization,
        QuestionnaireMailerInterface $mailer,
        string $publicSiteBaseUrl
    ) {
        $publicUrlResolver = new QuestionnairePublicUrlResolver($productionContext, $publicSiteBaseUrl);
        $editPolicy = new QuestionEditPolicy($responses);

        $createQuestionnaire = new CreateQuestionnaireUseCase($questionnaires, $productionContext, $identity, $authorization, $publicUrlResolver);
        $getQuestionnaire = new GetQuestionnaireUseCase($questionnaires, $questions, $productionContext, $identity, $authorization, $publicUrlResolver);
        $updateQuestionnaire = new UpdateQuestionnaireUseCase($questionnaires, $questions, $productionContext, $identity, $authorization, $publicUrlResolver);
        $publishQuestionnaire = new PublishQuestionnaireUseCase($questionnaires, $questions, $productionContext, $identity, $authorization, $publicUrlResolver);
        $closeQuestionnaire = new CloseQuestionnaireUseCase($questionnaires, $questions, $productionContext, $identity, $authorization, $publicUrlResolver);
        $addQuestion = new AddQuestionUseCase($questionnaires, $questions, $productionContext, $identity, $authorization);
        $updateQuestion = new UpdateQuestionUseCase($questionnaires, $questions, $productionContext, $identity, $authorization, $editPolicy);
        $deleteQuestion = new DeleteQuestionUseCase($questionnaires, $questions, $productionContext, $identity, $authorization, $editPolicy);
        $reorderQuestions = new ReorderQuestionsUseCase($questionnaires, $questions, $productionContext, $identity, $authorization);
        $getResults = new GetQuestionnaireResultsUseCase($questionnaires, $questions, $responses, $productionContext, $identity, $authorization);
        $getPublicQuestionnaire = new GetPublicQuestionnaireUseCase($questionnaires, $questions, $productionContext);
        $submitResponse = new SubmitQuestionnaireResponseUseCase($questionnaires, $questions, $responses);

        $sendInvites = new SendQuestionnaireInvitesForPerformanceUseCase(
            $performances,
            $questionnaires,
            $reservations,
            $inviteRecords,
            $mailer,
            $publicUrlResolver,
            $productionContext
        );
        $this->performanceFinishedListener = new QuestionnairePerformanceFinishedListener($sendInvites);

        $this->restControllers = [
            new QuestionnaireRestController(
                $createQuestionnaire,
                $getQuestionnaire,
                $updateQuestionnaire,
                $publishQuestionnaire,
                $closeQuestionnaire,
                $addQuestion,
                $updateQuestion,
                $deleteQuestion,
                $reorderQuestions,
                $getResults,
                $getPublicQuestionnaire,
                $submitResponse
            ),
        ];
    }

    /**
     * @return array<int, object>
     */
    public function restControllers(): array
    {
        return $this->restControllers;
    }

    public function performanceFinishedListener(): QuestionnairePerformanceFinishedListener
    {
        return $this->performanceFinishedListener;
    }
}
