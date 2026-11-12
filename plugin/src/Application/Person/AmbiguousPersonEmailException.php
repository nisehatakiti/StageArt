<?php

declare(strict_types=1);

namespace StageArt\Application\Person;

use RuntimeException;

/**
 * StageArt メール招待によるProductionParticipant追加機能: thrown when an
 * email address resolves to more than one distinct Person across
 * EmailCredential/verified NotificationEmail (see
 * FindPersonByEmailUseCase) - an anomalous state this Use Case
 * deliberately refuses to resolve by picking one, per this round's
 * explicit instruction.
 */
final class AmbiguousPersonEmailException extends RuntimeException
{
}
