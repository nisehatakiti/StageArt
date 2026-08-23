<?php

declare(strict_types=1);

namespace StageArt\Application\PrintView;

final class ProductionPrintViewResult
{
    public string $productionId;
    public string $productionName;
    /** @var RehearsalPrintSectionResult[] */
    public array $sections;

    /**
     * @param RehearsalPrintSectionResult[] $sections
     */
    public function __construct(string $productionId, string $productionName, array $sections)
    {
        $this->productionId = $productionId;
        $this->productionName = $productionName;
        $this->sections = $sections;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'production_id' => $this->productionId,
            'production_name' => $this->productionName,
            'sections' => array_map(
                static fn (RehearsalPrintSectionResult $section): array => $section->toArray(),
                $this->sections
            ),
        ];
    }
}
