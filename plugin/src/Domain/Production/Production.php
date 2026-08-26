<?php

declare(strict_types=1);

namespace StageArt\Domain\Production;

use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Project\ProjectId;

/**
 * PrimaryManager is modeled as a direct field (primaryManagerPersonId)
 * rather than a separate Entity: Production.md draws it as a simple
 * one-level "Production -> PrimaryManager -> Person" chain with no
 * Status/audit fields of its own (unlike ProductionDelegate, which
 * explicitly has Status/CreatedBy/UpdatedBy), and states "一つの
 * Productionには、一人のPrimaryManagerが存在する" - a plain 1:1
 * reference is the most direct representation of that.
 */
final class Production
{
    private ProductionId $id;
    private ProjectId $projectId;
    private ProductionName $name;
    private ?ProductionSlug $slug;
    private ?string $titleHeading;
    private ProductionStatus $status;
    private ?DateTimeImmutable $publishedAt;
    private PersonId $primaryManagerPersonId;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    private function __construct(
        ProductionId $id,
        ProjectId $projectId,
        ProductionName $name,
        ?ProductionSlug $slug,
        ?string $titleHeading,
        ProductionStatus $status,
        ?DateTimeImmutable $publishedAt,
        PersonId $primaryManagerPersonId,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
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
    }

    /**
     * StageArt Web First Phase 2: `slug` is an optional trailing
     * parameter (matching `titleHeading`'s existing optionality), not a
     * required one - see Organization::create()'s matching docblock for
     * why this stays permissive at the Domain layer while
     * CreateProductionUseCase (the real onboarding entry point) is
     * where "a newly created Production must have a slug" is actually
     * enforced.
     */
    public static function create(
        ProjectId $projectId,
        ProductionName $name,
        PersonId $primaryManagerPersonId,
        ?string $titleHeading = null,
        ?ProductionSlug $slug = null
    ): self {
        $now = new DateTimeImmutable();

        return new self(
            ProductionId::generate(),
            $projectId,
            $name,
            $slug,
            self::normalizeTitleHeading($titleHeading),
            ProductionStatus::draft(),
            null,
            $primaryManagerPersonId,
            $now,
            $now
        );
    }

    public static function reconstitute(
        ProductionId $id,
        ProjectId $projectId,
        ProductionName $name,
        ?string $titleHeading,
        ProductionStatus $status,
        PersonId $primaryManagerPersonId,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
        ?ProductionSlug $slug = null,
        ?DateTimeImmutable $publishedAt = null
    ): self {
        return new self(
            $id,
            $projectId,
            $name,
            $slug,
            $titleHeading,
            $status,
            $publishedAt,
            $primaryManagerPersonId,
            $createdAt,
            $updatedAt
        );
    }

    public function rename(ProductionName $name): void
    {
        $this->name = $name;
        $this->touch();
    }

    public function changeSlug(ProductionSlug $slug): void
    {
        $this->slug = $slug;
        $this->touch();
    }

    /**
     * StageArt Web First Phase 2: separate from `status` (the strict
     * Lifecycle Action transition chain below) - this is specifically
     * public-page visibility, an orthogonal concern. A slug is required
     * to publish.
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
            throw new InvalidArgumentException('A Production must have a slug before it can be published.');
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

    /**
     * ProductionTitleHeadingPolicy.md: "自由入力文字列" (free-form text),
     * no length/format validation mandated - only that it is never
     * concatenated into the Title ("公演肩書は公演タイトルの一部として連結
     * 保存しない", enforced structurally here by storing it as a wholly
     * separate field) and that unset means "display Title only" (empty
     * string is normalized to null, matching Organization's
     * changeDescription()/changeType() nullable-field convention).
     */
    public function changeTitleHeading(?string $titleHeading): void
    {
        $this->titleHeading = self::normalizeTitleHeading($titleHeading);
        $this->touch();
    }

    private static function normalizeTitleHeading(?string $titleHeading): ?string
    {
        if ($titleHeading === null) {
            return null;
        }

        $trimmed = trim($titleHeading);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Phase 6.1: ProductionLifecycle.md's confirmed Business Action model
     * ("Production Statusは、単純な設定値の直接書き換えによって任意に変更する
     * ことを基本としない...Lifecycle Transition Action" + explicit
     * transition table). Each public method below represents exactly one
     * named Business Action, matching the Blueprint's own DRAFT ->
     * PLANNING -> ACTIVE -> COMPLETED -> ARCHIVED chain plus the
     * separate CANCELLED terminal state. There is no longer a generic
     * "set to any Status" method - see REST/Application layer §12 for
     * how PUT /productions/{id} was changed accordingly.
     *
     * The Blueprint transition table itself does not name literal Action
     * verbs (only target Status labels and Japanese stage names: 企画/
     * 予算策定/制作.../決算完了/Archive) - method names below are derived
     * directly from the target Status, not invented business vocabulary.
     */
    private const ALLOWED_TRANSITIONS = [
        ProductionStatus::DRAFT => [ProductionStatus::PLANNING, ProductionStatus::CANCELLED],
        ProductionStatus::PLANNING => [ProductionStatus::ACTIVE, ProductionStatus::CANCELLED],
        ProductionStatus::ACTIVE => [ProductionStatus::COMPLETED, ProductionStatus::CANCELLED],
        ProductionStatus::COMPLETED => [ProductionStatus::ARCHIVED],
        ProductionStatus::ARCHIVED => [],
        ProductionStatus::CANCELLED => [],
    ];

    /**
     * DRAFT -> PLANNING ("企画" -> "予算策定").
     */
    public function startPlanning(): void
    {
        $this->transitionTo(ProductionStatus::PLANNING);
    }

    /**
     * PLANNING -> ACTIVE ("予算策定" -> "制作"). ACTIVE itself covers
     * 制作/稽古・広報・販売/公演/精算 as one Status per
     * ProductionLifecycle.md's "ACTIVE Scope" - none of those sub-phases
     * are separate Statuses.
     */
    public function activate(): void
    {
        $this->transitionTo(ProductionStatus::ACTIVE);
    }

    /**
     * ACTIVE -> COMPLETED ("精算" -> "決算完了"). ProductionLifecycle.md's
     * "Completion Rule" requires 決算完了 (Accounting settlement
     * completion) before this transition, but defers the concrete
     * completion condition to Accounting Domain ("具体的な決算完了条件
     * ...はAccounting Domainで定義する") - Accounting Domain does not yet
     * define a computable Settlement-completion check (Production
     * Settlement is unimplemented; see this Phase's Open Items). No
     * automated settlement verification is enforced here; per
     * ProductionLifecycle.md's own "GO" model ("管理者がProductionの状況を
     * 確認し、次の段階へ進めることを明示的に承認した時点で実行する"), calling
     * this Action itself is the PrimaryManager's explicit judgment that
     * settlement is complete - a real computed Guard should replace this
     * once Production Settlement exists.
     */
    public function complete(): void
    {
        $this->transitionTo(ProductionStatus::COMPLETED);
    }

    /**
     * COMPLETED -> ARCHIVED ("決算完了" -> "Archive"). Blueprint's
     * Completion Rule places its settlement gate on the ACTIVE ->
     * COMPLETED transition (see complete() above), not here -
     * ProductionLifecycle.md's "Archive Rule" only requires "必要な参照
     * 期間を経て" (an appropriate reference/retention period), which is an
     * organizational judgment, not a system-computable precondition.
     * Phase 1-era code guarded this specific transition against a
     * missing Production Settlement instead; re-reading
     * ProductionLifecycle.md's now-confirmed wording, that guard was
     * attached to the wrong transition. This method intentionally does
     * not carry it forward - see this Phase's report for the discovered
     * mismatch and the reasoning above.
     */
    public function archive(): void
    {
        $this->transitionTo(ProductionStatus::ARCHIVED);
    }

    /**
     * CANCELLED is reachable from DRAFT/PLANNING/ACTIVE only (not from
     * COMPLETED/ARCHIVED, which represent a finished Production).
     * ProductionLifecycle.md does not name who may invoke Cancel
     * specifically - Application layer authorization mirrors the other
     * four Lifecycle Actions (PrimaryManager-only), since
     * ProductionDelegatePolicy.md's Lifecycle Relationship table already
     * places every other Lifecycle transition there and Cancel is
     * presented as part of the same Lifecycle concept, not a separate
     * one.
     */
    public function cancel(): void
    {
        $this->transitionTo(ProductionStatus::CANCELLED);
    }

    private function transitionTo(string $target): void
    {
        $allowed = self::ALLOWED_TRANSITIONS[$this->status->toString()] ?? [];

        if (! in_array($target, $allowed, true)) {
            throw new InvalidArgumentException(
                "Production cannot transition from {$this->status->toString()} to {$target}."
            );
        }

        $this->status = ProductionStatus::fromString($target);
        $this->touch();
    }

    public function changePrimaryManager(PersonId $primaryManagerPersonId): void
    {
        $this->primaryManagerPersonId = $primaryManagerPersonId;
        $this->touch();
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function id(): ProductionId
    {
        return $this->id;
    }

    public function projectId(): ProjectId
    {
        return $this->projectId;
    }

    public function name(): ProductionName
    {
        return $this->name;
    }

    public function slug(): ?ProductionSlug
    {
        return $this->slug;
    }

    public function publishedAt(): ?DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function titleHeading(): ?string
    {
        return $this->titleHeading;
    }

    public function status(): ProductionStatus
    {
        return $this->status;
    }

    public function primaryManagerPersonId(): PersonId
    {
        return $this->primaryManagerPersonId;
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
