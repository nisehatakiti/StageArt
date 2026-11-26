<?php

declare(strict_types=1);

namespace StageArt\Application\Authentication;

use InvalidArgumentException;

/** The address given to the account check is empty or not an e-mail address (422). */
final class InvalidAccountCheckEmailException extends InvalidArgumentException
{
}
