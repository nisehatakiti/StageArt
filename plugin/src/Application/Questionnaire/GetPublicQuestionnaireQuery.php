<?php

declare(strict_types=1);

namespace StageArt\Application\Questionnaire;

final class GetPublicQuestionnaireQuery
{
    public string $productionSlug;

    public function __construct(string $productionSlug)
    {
        $this->productionSlug = $productionSlug;
    }
}
