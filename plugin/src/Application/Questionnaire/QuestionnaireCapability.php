<?php

declare(strict_types=1);

namespace StageArt\Application\Questionnaire;

/**
 * §33: "Questionnaire管理権限 = Production管理権限" - the PrimaryManager
 * always passes this via AuthorizationContract::canForProduction()'s
 * Core-level rule (see ProductionAuthorizationService::hasProductionCapability()).
 * A single umbrella Capability (not one per operation, unlike
 * PerformanceCapability's Create/Update/Cancel split) matches
 * RehearsalCapability::MANAGE's precedent for a module whose Blueprint
 * gives it one flat "管理できる/できない" line rather than an enumerated
 * per-action Permission vocabulary.
 */
final class QuestionnaireCapability
{
    public const MANAGE = 'Questionnaire.Manage';

    private function __construct()
    {
    }
}
