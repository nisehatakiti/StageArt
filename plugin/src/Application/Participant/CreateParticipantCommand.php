<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

/**
 * StageArt Phase 1 (docs/21-MemberManagementScreen.md §2.4): `subjectId`
 * is required for PERSON/ORGANIZATION but ignored for NAME_ONLY (a
 * fresh placeholder is generated instead - see
 * Participant::createNameOnly()); `displayName`/`remarks` are optional
 * and only `displayName` is meaningful for NAME_ONLY.
 */
final class CreateParticipantCommand
{
    public string $productionId;
    public int $requestedByWordPressUserId;
    public string $subjectType;
    public ?string $subjectId;
    public string $participantType;
    public ?string $displayName;
    public ?string $remarks;

    public function __construct(
        string $productionId,
        int $requestedByWordPressUserId,
        string $subjectType,
        ?string $subjectId,
        string $participantType,
        ?string $displayName = null,
        ?string $remarks = null
    ) {
        $this->productionId = $productionId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->subjectType = $subjectType;
        $this->subjectId = $subjectId;
        $this->participantType = $participantType;
        $this->displayName = $displayName;
        $this->remarks = $remarks;
    }
}
