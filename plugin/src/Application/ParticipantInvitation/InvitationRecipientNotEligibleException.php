<?php

declare(strict_types=1);

namespace StageArt\Application\ParticipantInvitation;

use RuntimeException;

/**
 * R14-B §4.3 / §7.2 (403): the logged-in user cannot answer invitations -
 * no StageArt Person, a UserAccount that is not ACTIVE (SUSPENDED /
 * DISABLED), or no verified address at all. Says nothing about any
 * invitation (its existence, state or recipient).
 */
final class InvitationRecipientNotEligibleException extends RuntimeException
{
}
