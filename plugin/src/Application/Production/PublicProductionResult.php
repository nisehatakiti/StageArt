<?php

declare(strict_types=1);

namespace StageArt\Application\Production;

use LogicException;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Production\Production;

/**
 * StageArt Web First Phase 2: deliberately narrower than
 * ProductionResult - served to unauthenticated visitors of
 * `stageart.top/{organization-slug}/{production-slug}`, so it never
 * carries `status`, `primary_manager_person_id`, or other internal/
 * management-only fields. Includes the parent Organization's own public
 * identity (resolved server-side via Production -> Project ->
 * Organization) since the public page's own breadcrumb/branding needs
 * it and the client should never have to make a second round trip just
 * to render "which theatre company is this".
 *
 * StageArt Web Completion Audit (Production公開ページ反映問題): the
 * per-section fields below (description/flyer/venue/schedule/script&
 * direction) are each independently gated by their own `isXPublished()`
 * Domain method (a time-comparison against that section's own
 * `X_published_at`, exactly like the whole-page `isPublished()`/
 * `publishedAt` pair above) - a section reads as `null` here whenever
 * its own gate is false, regardless of whether the underlying Production
 * field itself is set. Member/participant info is deliberately not
 * included here yet - see the audit's findings for why.
 */
final class PublicProductionResult
{
    public string $id;
    public string $name;
    public string $slug;
    public ?string $titleHeading;
    public string $publishedAt;
    public ?string $description;
    public ?string $flyerUrl;
    public ?string $venueName;
    public ?string $scheduleStartDate;
    public ?string $scheduleEndDate;
    public ?string $scriptCredit;
    public ?string $directionCredit;
    public string $organizationId;
    public string $organizationName;
    public string $organizationSlug;

    private function __construct(
        string $id,
        string $name,
        string $slug,
        ?string $titleHeading,
        string $publishedAt,
        ?string $description,
        ?string $flyerUrl,
        ?string $venueName,
        ?string $scheduleStartDate,
        ?string $scheduleEndDate,
        ?string $scriptCredit,
        ?string $directionCredit,
        string $organizationId,
        string $organizationName,
        string $organizationSlug
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->slug = $slug;
        $this->titleHeading = $titleHeading;
        $this->publishedAt = $publishedAt;
        $this->description = $description;
        $this->flyerUrl = $flyerUrl;
        $this->venueName = $venueName;
        $this->scheduleStartDate = $scheduleStartDate;
        $this->scheduleEndDate = $scheduleEndDate;
        $this->scriptCredit = $scriptCredit;
        $this->directionCredit = $directionCredit;
        $this->organizationId = $organizationId;
        $this->organizationName = $organizationName;
        $this->organizationSlug = $organizationSlug;
    }

    /**
     * @throws LogicException if the Production has no slug or is not
     *                         published, or the Organization has no
     *                         slug - callers must check these first
     *                         (see GetPublicProductionBySlugUseCase).
     */
    public static function fromDomain(Production $production, Organization $organization): self
    {
        $slug = $production->slug();
        $publishedAt = $production->publishedAt();
        $organizationSlug = $organization->slug();

        if ($slug === null || $publishedAt === null || $organizationSlug === null) {
            throw new LogicException('Cannot build a PublicProductionResult for an unpublished Production/Organization.');
        }

        return new self(
            $production->id()->toString(),
            $production->name()->toString(),
            $slug->toString(),
            $production->titleHeading(),
            $publishedAt->format(DATE_ATOM),
            $production->isDescriptionPublished() ? $production->description() : null,
            $production->isFlyerPublished() ? $production->flyerUrl() : null,
            $production->isVenuePublished() ? $production->venueName() : null,
            $production->isSchedulePublished() ? $production->scheduleStartDate()?->format('Y-m-d') : null,
            $production->isSchedulePublished() ? $production->scheduleEndDate()?->format('Y-m-d') : null,
            $production->isScriptDirectionPublished() ? $production->scriptCredit() : null,
            $production->isScriptDirectionPublished() ? $production->directionCredit() : null,
            $organization->id()->toString(),
            $organization->name()->toString(),
            $organizationSlug->toString()
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'title_heading' => $this->titleHeading,
            'published_at' => $this->publishedAt,
            'description' => $this->description,
            'flyer_url' => $this->flyerUrl,
            'venue_name' => $this->venueName,
            'schedule_start_date' => $this->scheduleStartDate,
            'schedule_end_date' => $this->scheduleEndDate,
            'script_credit' => $this->scriptCredit,
            'direction_credit' => $this->directionCredit,
            'organization' => [
                'id' => $this->organizationId,
                'name' => $this->organizationName,
                'slug' => $this->organizationSlug,
            ],
        ];
    }
}
