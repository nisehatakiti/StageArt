<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Participant\ParticipantStatus;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionStatus;

/**
 * PR #7 R14-B stage 6 (design doc §7.3, D11 / U3): what a viewer of the
 * Participant APIs (list, get, update's response) may see of each row.
 * Keeping the row (D11 - nothing is deleted) and seeing it are separate:
 * this only decides what is returned.
 *
 * Viewer: PRIMARY_MANAGER; MANAGER (canManageParticipants - 代理人,
 * PARTICIPANT_MANAGER); READER (canReadParticipants only - the read-only
 * REHEARSAL_MANAGER).
 *
 * Production PLANNING / ACTIVE:
 *
 * | field                          | PRIMARY_MANAGER / MANAGER | READER       |
 * |--------------------------------|---------------------------|--------------|
 * | display_name, status           | yes                       | yes          |
 * | family / given name            | ACTIVE, INACTIVE rows     | ACTIVE, INACTIVE rows |
 * | email                          | ACTIVE, INACTIVE rows     | no           |
 * | enrollment_source, consent_*   | yes                       | no           |
 * | CANCELLED/WITHDRAWN/REJECTED   | display_name + status only| row omitted  |
 *
 * Production COMPLETED / ARCHIVED / CANCELLED: the same, except that the
 * family / given name is no longer shown to a READER and the email is
 * shown to the PrimaryManager only (U3).
 *
 * Decisions confirmed by the user before stage 6 (not spelled out in §7.3):
 * - PENDING / DRAFT rows follow the table literally: no name or email
 *   (those are "ACTIVE・INACTIVE のみ"); the row, display_name, status and
 *   (for a manager) the consent fields are returned.
 * - 本人: the viewer's own row (PERSON, subject = the viewer) is returned
 *   in full whatever the viewer's role or the Production's status.
 * - After the Production ends, the consent fields and the ended rows keep
 *   the PLANNING / ACTIVE rules.
 * - An ended row shown to a manager also withholds `subject_id` (the
 *   Person id) and `remarks` - see ParticipantDisclosure.
 */
final class ParticipantDisclosurePolicy
{
    public const VIEWER_PRIMARY_MANAGER = 'PRIMARY_MANAGER';
    public const VIEWER_MANAGER = 'MANAGER';
    public const VIEWER_READER = 'READER';

    private const CLOSED_PRODUCTION_STATUSES = [
        ProductionStatus::COMPLETED,
        ProductionStatus::ARCHIVED,
        ProductionStatus::CANCELLED,
    ];

    private const ENDED_PARTICIPANT_STATUSES = [
        ParticipantStatus::CANCELLED,
        ParticipantStatus::WITHDRAWN,
        ParticipantStatus::REJECTED,
    ];

    private const ENROLLED_PARTICIPANT_STATUSES = [
        ParticipantStatus::ACTIVE,
        ParticipantStatus::INACTIVE,
    ];

    /**
     * @return string|null one of the VIEWER_* constants, or null when the
     *   Person may not read the Participants at all
     */
    public static function viewerOf(ProductionAuthorizationService $authorization, Person $viewer, Production $production): ?string
    {
        if ($authorization->isPrimaryManager($viewer, $production)) {
            return self::VIEWER_PRIMARY_MANAGER;
        }

        if ($authorization->canManageParticipants($viewer, $production)) {
            return self::VIEWER_MANAGER;
        }

        if ($authorization->canReadParticipants($viewer, $production)) {
            return self::VIEWER_READER;
        }

        return null;
    }

    /**
     * @return ParticipantDisclosure|null null when the row is not returned at all
     */
    public static function disclosureFor(Participant $participant, Production $production, string $viewerKind, Person $viewer): ?ParticipantDisclosure
    {
        if (
            $participant->subjectType()->equals(ParticipantSubjectType::person())
            && $participant->subjectId() === $viewer->id()->toString()
        ) {
            return ParticipantDisclosure::full();
        }

        $isReader = $viewerKind === self::VIEWER_READER;
        $status = $participant->status()->toString();

        if (in_array($status, self::ENDED_PARTICIPANT_STATUSES, true)) {
            return $isReader ? null : ParticipantDisclosure::statusOnly();
        }

        $enrolled = in_array($status, self::ENROLLED_PARTICIPANT_STATUSES, true);
        $closed = in_array($production->status()->toString(), self::CLOSED_PRODUCTION_STATUSES, true);

        return ParticipantDisclosure::of(
            $enrolled && (! $closed || ! $isReader),
            $enrolled && ! $isReader && (! $closed || $viewerKind === self::VIEWER_PRIMARY_MANAGER),
            ! $isReader
        );
    }
}
