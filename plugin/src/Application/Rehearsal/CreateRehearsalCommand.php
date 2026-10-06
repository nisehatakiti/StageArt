<?php

declare(strict_types=1);

namespace StageArt\Application\Rehearsal;

final class CreateRehearsalCommand
{
    public string $productionId;
    public int $requestedByWordPressUserId;
    public ?string $title;
    public ?string $description;
    public ?string $startDateTime;
    public ?string $endDateTime;
    public ?string $timezone;
    public ?string $location;
    /** Phase 7 (Rehearsal仕様整合): "予定稽古には回答期限を設定する" -
     * optional, ISO-8601-parseable string; null means no deadline. */
    public ?string $responseDeadline;
    /** @var string[] PersonId strings of the Production Participants who
     * become this Rehearsal's Attendance targets - not "every active
     * Production member" (that auto-targeting was removed; see
     * CreateRehearsalUseCase's own docblock). Defaults to an empty list
     * (no targets) rather than null so callers who genuinely don't pass
     * anything never fall back to the old auto-all-members behavior. */
    public array $targetPersonIds;
    /** StageArt 予定・活動一般化調査: one of RehearsalActivityType's
     * values (REHEARSAL/PRACTICE/SELF_PRACTICE/WORKSHOP/MEETING/
     * DISCUSSION/EVENT/OTHER); null defers to the Domain's own default
     * (REHEARSAL) - existing callers that never pass this keep working
     * unchanged. */
    public ?string $activityType;

    /**
     * @param string[]|null $targetPersonIds
     */
    public function __construct(
        string $productionId,
        int $requestedByWordPressUserId,
        ?string $title,
        ?string $description,
        ?string $startDateTime,
        ?string $endDateTime,
        ?string $timezone,
        ?string $location,
        ?array $targetPersonIds = null,
        ?string $responseDeadline = null,
        ?string $activityType = null
    ) {
        $this->productionId = $productionId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->title = $title;
        $this->description = $description;
        $this->startDateTime = $startDateTime;
        $this->endDateTime = $endDateTime;
        $this->timezone = $timezone;
        $this->location = $location;
        $this->targetPersonIds = $targetPersonIds ?? [];
        $this->responseDeadline = $responseDeadline;
        $this->activityType = $activityType;
    }
}
