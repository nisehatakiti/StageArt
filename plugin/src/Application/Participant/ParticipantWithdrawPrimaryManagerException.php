<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

use RuntimeException;

/** R14-B §7.2 (409 stageart_participant_withdraw_primary_manager): the PrimaryManager must hand over the role first. */
final class ParticipantWithdrawPrimaryManagerException extends RuntimeException
{
}
