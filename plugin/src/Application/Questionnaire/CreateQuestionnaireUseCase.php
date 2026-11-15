<?php

declare(strict_types=1);

namespace StageArt\Application\Questionnaire;

use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Questionnaire\Questionnaire;
use StageArt\Domain\Questionnaire\QuestionnaireRepositoryInterface;

/**
 * §31/AC-03: refuses a second Questionnaire for a Production that already
 * has one - V1's "1 Production : 1 Questionnaire" rule.
 */
final class CreateQuestionnaireUseCase
{
    private QuestionnaireRepositoryInterface $questionnaires;
    private ProductionContextContract $productionContext;
    private IdentityContract $identity;
    private AuthorizationContract $authorization;
    private QuestionnairePublicUrlResolver $publicUrlResolver;

    public function __construct(
        QuestionnaireRepositoryInterface $questionnaires,
        ProductionContextContract $productionContext,
        IdentityContract $identity,
        AuthorizationContract $authorization,
        QuestionnairePublicUrlResolver $publicUrlResolver
    ) {
        $this->questionnaires = $questionnaires;
        $this->productionContext = $productionContext;
        $this->identity = $identity;
        $this->authorization = $authorization;
        $this->publicUrlResolver = $publicUrlResolver;
    }

    public function execute(CreateQuestionnaireCommand $command): QuestionnaireResult
    {
        $requesterId = $this->identity->resolveCurrentPersonId($command->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new QuestionnaireAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $productionId = ProductionId::fromString($command->productionId);
        $production = $this->productionContext->getProduction($productionId);

        if (! $production) {
            throw new ProductionNotFoundException($command->productionId);
        }

        if (! $this->authorization->canForProduction($requesterId, $productionId, QuestionnaireCapability::MANAGE)) {
            throw new QuestionnaireAccessDeniedException(
                'Only the PrimaryManager or an authorized ProductionDelegate can create this Production\'s Questionnaire.'
            );
        }

        if ($this->questionnaires->findByProductionId($productionId) !== null) {
            throw new QuestionnaireAlreadyExistsException($command->productionId);
        }

        $questionnaire = Questionnaire::create($productionId, $command->title, $command->description, $requesterId);

        $this->questionnaires->save($questionnaire);

        return QuestionnaireResult::fromDomain(
            $questionnaire,
            $this->publicUrlResolver->resolve($productionId) ?? '',
            []
        );
    }
}
