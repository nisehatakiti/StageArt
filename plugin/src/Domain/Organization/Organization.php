<?php

declare(strict_types=1);

namespace StageArt\Domain\Organization;

use DateTimeImmutable;
use InvalidArgumentException;

final class Organization
{
    private OrganizationId $id;
    private OrganizationName $name;
    private ?OrganizationSlug $slug;
    private ?string $type;
    private ?string $description;
    private OrganizationStatus $status;
    private ?DateTimeImmutable $publishedAt;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    private function __construct(
        OrganizationId $id,
        OrganizationName $name,
        ?OrganizationSlug $slug,
        ?string $type,
        ?string $description,
        OrganizationStatus $status,
        ?DateTimeImmutable $publishedAt,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->slug = $slug;
        $this->type = $type;
        $this->description = $description;
        $this->status = $status;
        $this->publishedAt = $publishedAt;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    /**
     * StageArt Web First Phase 2: `slug` is an optional trailing
     * parameter here (matching `type`/`description`'s existing
     * optionality), not a required one - Domain-level `create()` stays
     * permissive/composable, matching every other Aggregate's
     * `create()` in this codebase (dozens of unrelated tests across
     * other features construct a bare `Organization::create($name)`
     * fixture and must keep working unchanged). "A newly onboarded
     * Organization must have a slug" is a real business-flow
     * requirement, but it belongs in `CreateOrganizationUseCase` (the
     * actual onboarding entry point), which validates and constructs
     * the `OrganizationSlug` VO before calling here - not baked into
     * this factory's own signature.
     */
    public static function create(
        OrganizationName $name,
        ?string $type = null,
        ?string $description = null,
        ?OrganizationSlug $slug = null
    ): self {
        $now = new DateTimeImmutable();

        return new self(
            OrganizationId::generate(),
            $name,
            $slug,
            $type,
            $description,
            OrganizationStatus::active(),
            null,
            $now,
            $now
        );
    }

    public static function reconstitute(
        OrganizationId $id,
        OrganizationName $name,
        ?string $type,
        ?string $description,
        OrganizationStatus $status,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
        ?OrganizationSlug $slug = null,
        ?DateTimeImmutable $publishedAt = null
    ): self {
        return new self($id, $name, $slug, $type, $description, $status, $publishedAt, $createdAt, $updatedAt);
    }

    public function rename(OrganizationName $name): void
    {
        $this->name = $name;
        $this->touch();
    }

    public function changeSlug(OrganizationSlug $slug): void
    {
        $this->slug = $slug;
        $this->touch();
    }

    /**
     * StageArt Web First Phase 2: separate from `status` (ACTIVE/
     * INACTIVE/ARCHIVED, an operational lifecycle concern) - this is
     * specifically public-page visibility. A slug is required to
     * publish (an unpublished/no-slug Organization has no public URL to
     * serve).
     */
    /**
     * StageArt Publication State Model
     * (docs/04-DomainModel/PublicationStateModel.md): `$at` defaults to
     * now (all pre-existing call sites are unaffected), but a caller can
     * pass a future `DateTimeImmutable` to schedule publication -
     * `isPublished()` compares against the current time on every read,
     * so a future `$at` naturally reads as SCHEDULED (not yet visible)
     * until that moment passes, with no CRON/background job needed.
     */
    public function publish(?DateTimeImmutable $at = null): void
    {
        if ($this->slug === null) {
            throw new InvalidArgumentException('An Organization must have a slug before it can be published.');
        }

        $this->publishedAt = $at ?? new DateTimeImmutable();
        $this->touch();
    }

    public function unpublish(): void
    {
        $this->publishedAt = null;
        $this->touch();
    }

    /**
     * Time-comparison, not a mere null-check (Publication State Model,
     * see publish()'s docblock) - a future `publishedAt` (SCHEDULED)
     * reads as not-yet-published until that moment passes.
     */
    public function isPublished(): bool
    {
        return $this->publishedAt !== null && $this->publishedAt <= new DateTimeImmutable();
    }

    public function changeType(?string $type): void
    {
        $this->type = $type;
        $this->touch();
    }

    public function changeDescription(?string $description): void
    {
        $this->description = $description;
        $this->touch();
    }

    public function activate(): void
    {
        $this->status = OrganizationStatus::active();
        $this->touch();
    }

    public function deactivate(): void
    {
        $this->status = OrganizationStatus::fromString(OrganizationStatus::INACTIVE);
        $this->touch();
    }

    public function archive(): void
    {
        $this->status = OrganizationStatus::fromString(OrganizationStatus::ARCHIVED);
        $this->touch();
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function id(): OrganizationId
    {
        return $this->id;
    }

    public function name(): OrganizationName
    {
        return $this->name;
    }

    public function slug(): ?OrganizationSlug
    {
        return $this->slug;
    }

    public function publishedAt(): ?DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function type(): ?string
    {
        return $this->type;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function status(): OrganizationStatus
    {
        return $this->status;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
