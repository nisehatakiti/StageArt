<?php

declare(strict_types=1);

namespace StageArt\Application\Questionnaire;

use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Production\ProductionId;

/**
 * §5/§19/§49/§62-禁止3-5: the single place that builds a Questionnaire's
 * public URL - `{publicSiteBaseUrl}/{organization-slug}/{production-slug}/
 * questionnaire`, with absolutely no query string, token, or id appended.
 * Every caller (admin "公開URL確認"/QR, and the invite Email) goes through
 * this one method, which is why the Email URL and the QR URL are always
 * byte-identical (AC-54).
 */
final class QuestionnairePublicUrlResolver
{
    private ProductionContextContract $productionContext;
    private string $publicSiteBaseUrl;

    public function __construct(ProductionContextContract $productionContext, string $publicSiteBaseUrl)
    {
        $this->productionContext = $productionContext;
        $this->publicSiteBaseUrl = rtrim($publicSiteBaseUrl, '/');
    }

    public function resolve(ProductionId $productionId): ?string
    {
        $slugs = $this->productionContext->getProductionPublicSlugs($productionId);

        if ($slugs === null) {
            return null;
        }

        return sprintf(
            '%s/%s/%s/questionnaire',
            $this->publicSiteBaseUrl,
            rawurlencode($slugs->organizationSlug),
            rawurlencode($slugs->productionSlug)
        );
    }
}
