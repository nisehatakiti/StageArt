<?php

declare(strict_types=1);

namespace StageArt\Application\Production;

use StageArt\Domain\Production\Production;
use StageArt\Domain\ProductionDelegate\ProductionDelegate;

final class ProductionResult
{
    public string $id;
    public string $projectId;
    public string $name;
    public ?string $slug;
    public ?string $titleHeading;
    public string $status;
    public ?string $publishedAt;
    public string $primaryManagerPersonId;
    public string $createdAt;
    public string $updatedAt;
    public bool $isPrimaryManager;
    public ?string $delegateRole;
    public ?string $description;
    public ?string $descriptionPublishedAt;
    public ?string $flyerUrl;
    public ?string $flyerPublishedAt;
    public ?string $venueName;
    public ?string $venuePublishedAt;
    public ?string $scheduleStartDate;
    public ?string $scheduleEndDate;
    public ?string $schedulePublishedAt;
    public ?string $scriptCredit;
    public ?string $directionCredit;
    public ?string $scriptDirectionPublishedAt;
    public ?string $memberInfoPublishedAt;
    public ?int $capacity;
    public ?string $performanceCommonRemarks;

    private function __construct(
        string $id,
        string $projectId,
        string $name,
        ?string $slug,
        ?string $titleHeading,
        string $status,
        ?string $publishedAt,
        string $primaryManagerPersonId,
        string $createdAt,
        string $updatedAt,
        bool $isPrimaryManager,
        ?string $delegateRole,
        ?string $description,
        ?string $descriptionPublishedAt,
        ?string $flyerUrl,
        ?string $flyerPublishedAt,
        ?string $venueName,
        ?string $venuePublishedAt,
        ?string $scheduleStartDate,
        ?string $scheduleEndDate,
        ?string $schedulePublishedAt,
        ?string $scriptCredit,
        ?string $directionCredit,
        ?string $scriptDirectionPublishedAt,
        ?string $memberInfoPublishedAt,
        ?int $capacity,
        ?string $performanceCommonRemarks
    ) {
        $this->id = $id;
        $this->projectId = $projectId;
        $this->name = $name;
        $this->slug = $slug;
        $this->titleHeading = $titleHeading;
        $this->status = $status;
        $this->publishedAt = $publishedAt;
        $this->primaryManagerPersonId = $primaryManagerPersonId;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
        $this->isPrimaryManager = $isPrimaryManager;
        $this->delegateRole = $delegateRole;
        $this->description = $description;
        $this->descriptionPublishedAt = $descriptionPublishedAt;
        $this->flyerUrl = $flyerUrl;
        $this->flyerPublishedAt = $flyerPublishedAt;
        $this->venueName = $venueName;
        $this->venuePublishedAt = $venuePublishedAt;
        $this->scheduleStartDate = $scheduleStartDate;
        $this->scheduleEndDate = $scheduleEndDate;
        $this->schedulePublishedAt = $schedulePublishedAt;
        $this->scriptCredit = $scriptCredit;
        $this->directionCredit = $directionCredit;
        $this->scriptDirectionPublishedAt = $scriptDirectionPublishedAt;
        $this->memberInfoPublishedAt = $memberInfoPublishedAt;
        $this->capacity = $capacity;
        $this->performanceCommonRemarks = $performanceCommonRemarks;
    }

    public static function fromDomain(
        Production $production,
        bool $isPrimaryManager,
        ?ProductionDelegate $activeDelegate
    ): self {
        return new self(
            $production->id()->toString(),
            $production->projectId()->toString(),
            $production->name()->toString(),
            $production->slug()?->toString(),
            $production->titleHeading(),
            $production->status()->toString(),
            $production->publishedAt()?->format(DATE_ATOM),
            $production->primaryManagerPersonId()->toString(),
            $production->createdAt()->format(DATE_ATOM),
            $production->updatedAt()->format(DATE_ATOM),
            $isPrimaryManager,
            $activeDelegate !== null ? $activeDelegate->role()->toString() : null,
            $production->description(),
            $production->descriptionPublishedAt()?->format(DATE_ATOM),
            $production->flyerUrl(),
            $production->flyerPublishedAt()?->format(DATE_ATOM),
            $production->venueName(),
            $production->venuePublishedAt()?->format(DATE_ATOM),
            $production->scheduleStartDate()?->format('Y-m-d'),
            $production->scheduleEndDate()?->format('Y-m-d'),
            $production->schedulePublishedAt()?->format(DATE_ATOM),
            $production->scriptCredit(),
            $production->directionCredit(),
            $production->scriptDirectionPublishedAt()?->format(DATE_ATOM),
            $production->memberInfoPublishedAt()?->format(DATE_ATOM),
            $production->capacity(),
            $production->performanceCommonRemarks()
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->projectId,
            'name' => $this->name,
            'slug' => $this->slug,
            'title_heading' => $this->titleHeading,
            'status' => $this->status,
            'published_at' => $this->publishedAt,
            'primary_manager_person_id' => $this->primaryManagerPersonId,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'is_primary_manager' => $this->isPrimaryManager,
            'delegate_role' => $this->delegateRole,
            'description' => $this->description,
            'description_published_at' => $this->descriptionPublishedAt,
            'flyer_url' => $this->flyerUrl,
            'flyer_published_at' => $this->flyerPublishedAt,
            'venue_name' => $this->venueName,
            'venue_published_at' => $this->venuePublishedAt,
            'schedule_start_date' => $this->scheduleStartDate,
            'schedule_end_date' => $this->scheduleEndDate,
            'schedule_published_at' => $this->schedulePublishedAt,
            'script_credit' => $this->scriptCredit,
            'direction_credit' => $this->directionCredit,
            'script_direction_published_at' => $this->scriptDirectionPublishedAt,
            'member_info_published_at' => $this->memberInfoPublishedAt,
            'capacity' => $this->capacity,
            'performance_common_remarks' => $this->performanceCommonRemarks,
        ];
    }
}
