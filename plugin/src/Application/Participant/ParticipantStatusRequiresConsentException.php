<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

use RuntimeException;

/**
 * R14-B §5.2 / §7.1 (stage 4): INACTIVE -> ACTIVE through PUT: only an invitation acceptance or an approved join request makes a Participant ACTIVE (P5). 422 `stageart_participant_status_requires_consent`.
 */
final class ParticipantStatusRequiresConsentException extends RuntimeException
{
}
