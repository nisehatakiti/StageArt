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

    /**
     * StageArt Phase 1 (docs/12-FunctionalStructure.md §20.5 "Public
     * Information and Publication Date/Time"): five independently-
     * publishable Production Information sections, each with its own
     * publication date/time separate from the whole-Production
     * `publishedAt` above (which gates the Production's public page
     * existing at all - these five gate each section's own visibility
     * once the page exists). 脚本/演出 share one publication date/time
     * per the Blueprint's screen layout (one "情報公開日時" field under a
     * single "脚本 / 演出" section, not two).
     */
    private ?string $description;
    private ?DateTimeImmutable $descriptionPublishedAt;
    private ?string $flyerUrl;
    private ?DateTimeImmutable $flyerPublishedAt;
    private ?string $venueName;
    private ?DateTimeImmutable $venuePublishedAt;
    private ?DateTimeImmutable $scheduleStartDate;
    private ?DateTimeImmutable $scheduleEndDate;
    private ?DateTimeImmutable $schedulePublishedAt;
    private ?string $scriptCredit;
    private ?string $directionCredit;
    private ?DateTimeImmutable $scriptDirectionPublishedAt;
    /**
     * §21.7 "Member Information Publication Date/Time": one publication
     * date/time for the entire Production member list (not per-member) -
     * a sixth independently-publishable section alongside description/
     * flyer/venue/schedule/script-direction above.
     */
    private ?DateTimeImmutable $memberInfoPublishedAt;

    /**
     * Phase 2 Performance基盤 instruction §9: internal management data
     * only ("公開ページには表示しない"), independent from any Ticket Type's
     * own sales quota/ノルマ concept. Nullable because pre-existing
     * Productions never had a value for it (non-destructive ALTER ADD
     * COLUMN, matching every other Phase 1 addition's own nullable
     * convention) - a null capacity blocks Performance creation until
     * explicitly set (see CreatePerformanceUseCase).
     */
    private ?int $capacity;

    /**
     * §14: "開場情報" and "全体備考" are deliberately NOT two separate
     * fields - one free-form "公演回共通備考" covering both, reused across
     * every Performance under this Production (§13 - Performance itself
     * carries no venue/common-notes field of its own).
     */
    private ?string $performanceCommonRemarks;

    /**
     * Phase 3 Ticket/Reservation基盤 §36: Ticket販売条件はProduction共通
     * 設定として保持する（Ticket Type単位・Performance単位ではない - §7/§8/
     * 指示書§36「Ticket Typeごとの販売開始日時は追加しない」「Performanceごとの
     * 販売終了絶対日時も追加しない」）。`ticketSalesEndRule`/
     * `ticketSalesEndParameter` はDomain\Ticket\SalesEndRuleの語彙
     * （DAY_BEFORE_AT_TIME/HOURS_BEFORE_START）を保存するが、Production
     * (Core) はTicket Module固有の型に依存しない設計とするため、ここでは
     * 素の文字列として保持し、実際の妥当性検証はApplication層で
     * SalesEndRule::fromStored()を通して行う。
     */
    private ?DateTimeImmutable $ticketPublicationAt;
    private ?DateTimeImmutable $ticketSalesStartAt;
    private ?string $ticketSalesEndRule;
    private ?string $ticketSalesEndParameter;

    /**
     * §19/§20: ノルマはProduction全体設定のみ（メンバー別設定は今回明示的に
     * 不採用 - Chapter 32 §5の上書き宣言と一致）。買取OFFの場合は
     * `quotaShortfallUnitPrice`を必ずnullへ正規化する（Domain/API/UIの
     * 一貫性を保つため、「買取OFFなのに単価が残る」状態を構造的に作れない
     * ようにする）。
     */
    private bool $quotaEnabled;
    private ?int $quotaCount;
    private bool $quotaBuybackEnabled;
    private ?int $quotaShortfallUnitPrice;

    /**
     * §27/§28: Ticket BackもProduction全体設定。`ticketBackRules`は
     * 優先順位付き複数条件（{priority,threshold,comparator,rate}の配列）
     * をJSON文字列として保持する - Opaqueな文字列ではなく、Application/
     * Domain\Ticket\TicketBackCondition側でパース・検証可能な構造化データ
     * （指示書§37「将来の計算・検証が困難になるようなOpaqueな文字列保存は
     * 避ける」）。
     */
    private ?string $ticketBackMode;
    private ?string $ticketBackRules;

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
        DateTimeImmutable $updatedAt,
        ?string $description = null,
        ?DateTimeImmutable $descriptionPublishedAt = null,
        ?string $flyerUrl = null,
        ?DateTimeImmutable $flyerPublishedAt = null,
        ?string $venueName = null,
        ?DateTimeImmutable $venuePublishedAt = null,
        ?DateTimeImmutable $scheduleStartDate = null,
        ?DateTimeImmutable $scheduleEndDate = null,
        ?DateTimeImmutable $schedulePublishedAt = null,
        ?string $scriptCredit = null,
        ?string $directionCredit = null,
        ?DateTimeImmutable $scriptDirectionPublishedAt = null,
        ?DateTimeImmutable $memberInfoPublishedAt = null,
        ?int $capacity = null,
        ?string $performanceCommonRemarks = null,
        ?DateTimeImmutable $ticketPublicationAt = null,
        ?DateTimeImmutable $ticketSalesStartAt = null,
        ?string $ticketSalesEndRule = null,
        ?string $ticketSalesEndParameter = null,
        bool $quotaEnabled = false,
        ?int $quotaCount = null,
        bool $quotaBuybackEnabled = false,
        ?int $quotaShortfallUnitPrice = null,
        ?string $ticketBackMode = null,
        ?string $ticketBackRules = null
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
        $this->ticketPublicationAt = $ticketPublicationAt;
        $this->ticketSalesStartAt = $ticketSalesStartAt;
        $this->ticketSalesEndRule = $ticketSalesEndRule;
        $this->ticketSalesEndParameter = $ticketSalesEndParameter;
        $this->quotaEnabled = $quotaEnabled;
        $this->quotaCount = $quotaCount;
        $this->quotaBuybackEnabled = $quotaBuybackEnabled;
        $this->quotaShortfallUnitPrice = $quotaShortfallUnitPrice;
        $this->ticketBackMode = $ticketBackMode;
        $this->ticketBackRules = $ticketBackRules;
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
            ProductionStatus::planning(),
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
        ?DateTimeImmutable $publishedAt = null,
        ?string $description = null,
        ?DateTimeImmutable $descriptionPublishedAt = null,
        ?string $flyerUrl = null,
        ?DateTimeImmutable $flyerPublishedAt = null,
        ?string $venueName = null,
        ?DateTimeImmutable $venuePublishedAt = null,
        ?DateTimeImmutable $scheduleStartDate = null,
        ?DateTimeImmutable $scheduleEndDate = null,
        ?DateTimeImmutable $schedulePublishedAt = null,
        ?string $scriptCredit = null,
        ?string $directionCredit = null,
        ?DateTimeImmutable $scriptDirectionPublishedAt = null,
        ?DateTimeImmutable $memberInfoPublishedAt = null,
        ?int $capacity = null,
        ?string $performanceCommonRemarks = null,
        ?DateTimeImmutable $ticketPublicationAt = null,
        ?DateTimeImmutable $ticketSalesStartAt = null,
        ?string $ticketSalesEndRule = null,
        ?string $ticketSalesEndParameter = null,
        bool $quotaEnabled = false,
        ?int $quotaCount = null,
        bool $quotaBuybackEnabled = false,
        ?int $quotaShortfallUnitPrice = null,
        ?string $ticketBackMode = null,
        ?string $ticketBackRules = null
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
            $updatedAt,
            $description,
            $descriptionPublishedAt,
            $flyerUrl,
            $flyerPublishedAt,
            $venueName,
            $venuePublishedAt,
            $scheduleStartDate,
            $scheduleEndDate,
            $schedulePublishedAt,
            $scriptCredit,
            $directionCredit,
            $scriptDirectionPublishedAt,
            $memberInfoPublishedAt,
            $capacity,
            $performanceCommonRemarks,
            $ticketPublicationAt,
            $ticketSalesStartAt,
            $ticketSalesEndRule,
            $ticketSalesEndParameter,
            $quotaEnabled,
            $quotaCount,
            $quotaBuybackEnabled,
            $quotaShortfallUnitPrice,
            $ticketBackMode,
            $ticketBackRules
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
     * StageArt Web First Phase 2: mostly separate from `status` (the
     * strict Lifecycle Action transition chain below) - this is
     * specifically public-page visibility. A slug is required to
     * publish.
     *
     * StageArt Production Lifecycle整理 instruction (this round): this
     * round's confirmed spec is "PLANNINGが非公開、ACTIVEが公開" and
     * "「公演を確定する」ことでPLANNING → ACTIVEとなり、公開される" - a
     * still-PLANNING Production can no longer be published directly
     * through this method (e.g. via the generic `published` toggle on
     * `UpdateProductionUseCase`), so "公演を確定する" (activate()) is the
     * one clear way to make a Production public. activate() itself calls
     * this method after already transitioning to ACTIVE (see its own
     * docblock), so this guard does not affect that call.
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

        if ($this->status->equals(ProductionStatus::fromString(ProductionStatus::PLANNING))) {
            throw new InvalidArgumentException(
                'A PLANNING Production cannot be published directly - confirm it ("公演を確定する") to become ACTIVE and public.'
            );
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
     * StageArt Web Completion Audit (Production公開ページ反映問題):
     * §20.5's five independently-publishable sections each need this
     * exact same "not a mere null-check, a time comparison" gate
     * isPublished() already established for the whole page - a future
     * per-section publication date/time (set the same way as the
     * whole-page one) must stay hidden until that moment passes, not the
     * instant it's saved. These are the one missing piece that made the
     * per-section `published_at` fields "write-only" - the Domain
     * already stored them, but nothing asked "is it actually visible
     * right now" the way isPublished() does for the page itself.
     */
    public function isDescriptionPublished(): bool
    {
        return $this->descriptionPublishedAt !== null && $this->descriptionPublishedAt <= new DateTimeImmutable();
    }

    public function isFlyerPublished(): bool
    {
        return $this->flyerPublishedAt !== null && $this->flyerPublishedAt <= new DateTimeImmutable();
    }

    public function isVenuePublished(): bool
    {
        return $this->venuePublishedAt !== null && $this->venuePublishedAt <= new DateTimeImmutable();
    }

    public function isSchedulePublished(): bool
    {
        return $this->schedulePublishedAt !== null && $this->schedulePublishedAt <= new DateTimeImmutable();
    }

    public function isScriptDirectionPublished(): bool
    {
        return $this->scriptDirectionPublishedAt !== null && $this->scriptDirectionPublishedAt <= new DateTimeImmutable();
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
     * StageArt Production Lifecycle整理 instruction: the confirmed
     * Lifecycle this round is PLANNING -> ACTIVE -> COMPLETED only. A
     * Production starts at PLANNING (see create()), so there is no
     * transition into PLANNING - it is never a target here. ARCHIVED and
     * CANCELLED are kept reachable exactly as before (their
     * retention/removal was not decided this round - see this round's
     * report); this table only drops the now-nonexistent DRAFT entry
     * point. There is no generic "set to any Status" method - see
     * REST/Application layer for how PUT /productions/{id} rejects a
     * `status` field.
     */
    private const ALLOWED_TRANSITIONS = [
        ProductionStatus::PLANNING => [ProductionStatus::ACTIVE, ProductionStatus::CANCELLED],
        ProductionStatus::ACTIVE => [ProductionStatus::COMPLETED, ProductionStatus::CANCELLED],
        ProductionStatus::COMPLETED => [ProductionStatus::ARCHIVED],
        ProductionStatus::ARCHIVED => [],
        ProductionStatus::CANCELLED => [],
    ];

    /**
     * PLANNING -> ACTIVE ("公演を確定する"). This round's confirmed
     * instruction: PLANNING is not public, ACTIVE is public, and this
     * exact transition is what makes a Production public - so this
     * Action also publishes the Production (`publish()`, defaulting to
     * "now"), connecting this round's Lifecycle instruction to the
     * pre-existing, otherwise Status-independent publish/unpublish
     * mechanism (see publish()'s own docblock). Whether a pre-existing
     * scheduled `publishedAt` (set independently before this Action runs)
     * should be preserved instead of being overwritten to "now" is not
     * determined by this round's instruction - see this round's report.
     */
    public function activate(): void
    {
        $this->transitionTo(ProductionStatus::ACTIVE);
        $this->publish();
    }

    /**
     * ACTIVE -> COMPLETED. What COMPLETED means beyond the Status value
     * itself (publish/edit behavior, any settlement precondition) is not
     * confirmed this round - this method intentionally does not add any
     * new precondition or side effect. Left exactly as before.
     */
    public function complete(): void
    {
        $this->transitionTo(ProductionStatus::COMPLETED);
    }

    /**
     * COMPLETED -> ARCHIVED. ARCHIVED's retention/removal was not decided
     * this round (see this round's report) - left exactly as before.
     */
    public function archive(): void
    {
        $this->transitionTo(ProductionStatus::ARCHIVED);
    }

    /**
     * CANCELLED is reachable from PLANNING/ACTIVE only (not from
     * COMPLETED/ARCHIVED, which represent a finished Production).
     * Left exactly as before - CANCELLED was not part of this round's
     * confirmed scope.
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

    /**
     * §20.5: each of these five sections is saved together with its own
     * publication date/time via the Production Information screen's
     * single [保存] button - one Domain call per section, value and
     * publish date/time set together, matching how the UI actually
     * submits them.
     */
    public function updateDescription(?string $description, ?DateTimeImmutable $publishedAt): void
    {
        $this->description = self::normalizeNullableString($description);
        $this->descriptionPublishedAt = $publishedAt;
        $this->touch();
    }

    /**
     * `flyerUrl` is the uploaded flyer's resulting URL - actual image
     * upload/normalization (Asset Policy's 1600px/600px pipeline) is
     * infrastructure this Phase does not build (no such pipeline exists
     * anywhere in this codebase yet for any Organization/Production/
     * Person image - a pre-existing, cross-cutting gap, not specific to
     * this field). This method only records where the flyer lives once
     * uploaded by whatever mechanism eventually provides one.
     */
    public function updateFlyer(?string $flyerUrl, ?DateTimeImmutable $publishedAt): void
    {
        $this->flyerUrl = self::normalizeNullableString($flyerUrl);
        $this->flyerPublishedAt = $publishedAt;
        $this->touch();
    }

    public function updateVenue(?string $venueName, ?DateTimeImmutable $publishedAt): void
    {
        $this->venueName = self::normalizeNullableString($venueName);
        $this->venuePublishedAt = $publishedAt;
        $this->touch();
    }

    public function updateSchedule(?DateTimeImmutable $startDate, ?DateTimeImmutable $endDate, ?DateTimeImmutable $publishedAt): void
    {
        if ($startDate !== null && $endDate !== null && $endDate < $startDate) {
            throw new InvalidArgumentException('Production schedule end date must not be before its start date.');
        }

        $this->scheduleStartDate = $startDate;
        $this->scheduleEndDate = $endDate;
        $this->schedulePublishedAt = $publishedAt;
        $this->touch();
    }

    public function updateScriptDirection(?string $scriptCredit, ?string $directionCredit, ?DateTimeImmutable $publishedAt): void
    {
        $this->scriptCredit = self::normalizeNullableString($scriptCredit);
        $this->directionCredit = self::normalizeNullableString($directionCredit);
        $this->scriptDirectionPublishedAt = $publishedAt;
        $this->touch();
    }

    public function updateMemberInfoPublishedAt(?DateTimeImmutable $publishedAt): void
    {
        $this->memberInfoPublishedAt = $publishedAt;
        $this->touch();
    }

    /**
     * Phase 2 Performance基盤 §11/§12: the trigger side of the mandatory
     * Production-capacity cascade - UpdateProductionUseCase calls this to
     * update Production's own stored value, then separately (via
     * PerformanceRepositoryInterface, in the same Transaction) overwrites
     * every child Performance's capacity to match. This method only
     * updates Production's own field; it deliberately does not reach into
     * Performance itself (§19 - "ProductionとPerformanceの責務を混在させ
     * ない" / "不必要に巨大なDomain Entityへ複数Aggregateの更新責務を集中さ
     * せない").
     */
    public function changeCapacity(?int $capacity): void
    {
        if ($capacity !== null && $capacity < 1) {
            throw new InvalidArgumentException('Production capacity must be a positive integer.');
        }

        $this->capacity = $capacity;
        $this->touch();
    }

    public function updatePerformanceCommonRemarks(?string $performanceCommonRemarks): void
    {
        $this->performanceCommonRemarks = self::normalizeNullableString($performanceCommonRemarks);
        $this->touch();
    }

    /**
     * Phase 3 §7/§9: Ticket情報公開日時とProduction共通の販売開始日時。
     * どちらも絶対日時のまま保存する（販売開始は指示書§7で明示的に
     * Production共通の絶対日時と確定している一方、公開日時は指示書§36の
     * DBカラム設計を正としてProduction側に置く - 指示書§5の記述は
     * Ticket管理機能が扱う概念一覧としての言及であり、フィールドの所属
     * Entityを規定するものではないと解釈した）。
     */
    public function updateTicketPublicationAt(?DateTimeImmutable $at): void
    {
        $this->ticketPublicationAt = $at;
        $this->touch();
    }

    public function updateTicketSalesStartAt(?DateTimeImmutable $at): void
    {
        $this->ticketSalesStartAt = $at;
        $this->touch();
    }

    /**
     * §8: 固定絶対日時ではなく「ルール」を保存する。ここでは`$rule`/
     * `$parameter`の妥当性検証を行わない - Ticket Moduleの
     * `Domain\Ticket\SalesEndRule::fromStored()`がPersistence直前に
     * 検証済みの値のみをここへ渡す前提とすることで、Production(Core)が
     * Ticket Module固有の語彙(DAY_BEFORE_AT_TIME/HOURS_BEFORE_START)に
     * 依存しないようにする。
     */
    public function updateTicketSalesEndRule(?string $rule, ?string $parameter): void
    {
        $this->ticketSalesEndRule = self::normalizeNullableString($rule);
        $this->ticketSalesEndParameter = self::normalizeNullableString($parameter);
        $this->touch();
    }

    /**
     * §19/§20: 買取OFFの場合は未達単価を必ずnullへ正規化する
     * （Domain層で条件を強制することで、UIだけの制御に依存しない - 指示書
     * §20「UIだけで入力欄を隠す実装にはしない」）。ノルマ設定なしの場合も
     * 同様にノルマ枚数をnullへ正規化する。
     */
    public function updateQuota(bool $enabled, ?int $count, bool $buybackEnabled, ?int $shortfallUnitPrice): void
    {
        if ($enabled && ($count === null || $count < 1)) {
            throw new InvalidArgumentException('Quota count must be a positive integer when quota is enabled.');
        }

        if ($enabled && $buybackEnabled && ($shortfallUnitPrice === null || $shortfallUnitPrice < 1)) {
            throw new InvalidArgumentException('Quota shortfall unit price must be a positive integer when buyback is enabled.');
        }

        $this->quotaEnabled = $enabled;
        $this->quotaCount = $enabled ? $count : null;
        $this->quotaBuybackEnabled = $enabled && $buybackEnabled;
        $this->quotaShortfallUnitPrice = ($enabled && $buybackEnabled) ? $shortfallUnitPrice : null;
        $this->touch();
    }

    /**
     * §27/§37: `$rulesJson`は事前にDomain\Ticket\TicketBackConditionの
     * 配列としてApplication層で検証済みのJSON文字列を渡す前提（Production
     * (Core)はTicket Module固有の条件Value Objectに依存しない）。
     */
    public function updateTicketBack(?string $mode, ?string $rulesJson): void
    {
        $this->ticketBackMode = self::normalizeNullableString($mode);
        $this->ticketBackRules = self::normalizeNullableString($rulesJson);
        $this->touch();
    }

    private static function normalizeNullableString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
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

    public function description(): ?string
    {
        return $this->description;
    }

    public function descriptionPublishedAt(): ?DateTimeImmutable
    {
        return $this->descriptionPublishedAt;
    }

    public function flyerUrl(): ?string
    {
        return $this->flyerUrl;
    }

    public function flyerPublishedAt(): ?DateTimeImmutable
    {
        return $this->flyerPublishedAt;
    }

    public function venueName(): ?string
    {
        return $this->venueName;
    }

    public function venuePublishedAt(): ?DateTimeImmutable
    {
        return $this->venuePublishedAt;
    }

    public function scheduleStartDate(): ?DateTimeImmutable
    {
        return $this->scheduleStartDate;
    }

    public function scheduleEndDate(): ?DateTimeImmutable
    {
        return $this->scheduleEndDate;
    }

    public function schedulePublishedAt(): ?DateTimeImmutable
    {
        return $this->schedulePublishedAt;
    }

    public function scriptCredit(): ?string
    {
        return $this->scriptCredit;
    }

    public function directionCredit(): ?string
    {
        return $this->directionCredit;
    }

    public function scriptDirectionPublishedAt(): ?DateTimeImmutable
    {
        return $this->scriptDirectionPublishedAt;
    }

    public function memberInfoPublishedAt(): ?DateTimeImmutable
    {
        return $this->memberInfoPublishedAt;
    }

    public function capacity(): ?int
    {
        return $this->capacity;
    }

    public function performanceCommonRemarks(): ?string
    {
        return $this->performanceCommonRemarks;
    }

    public function ticketPublicationAt(): ?DateTimeImmutable
    {
        return $this->ticketPublicationAt;
    }

    public function ticketSalesStartAt(): ?DateTimeImmutable
    {
        return $this->ticketSalesStartAt;
    }

    public function ticketSalesEndRule(): ?string
    {
        return $this->ticketSalesEndRule;
    }

    public function ticketSalesEndParameter(): ?string
    {
        return $this->ticketSalesEndParameter;
    }

    public function quotaEnabled(): bool
    {
        return $this->quotaEnabled;
    }

    public function quotaCount(): ?int
    {
        return $this->quotaCount;
    }

    public function quotaBuybackEnabled(): bool
    {
        return $this->quotaBuybackEnabled;
    }

    public function quotaShortfallUnitPrice(): ?int
    {
        return $this->quotaShortfallUnitPrice;
    }

    public function ticketBackMode(): ?string
    {
        return $this->ticketBackMode;
    }

    public function ticketBackRules(): ?string
    {
        return $this->ticketBackRules;
    }
}
