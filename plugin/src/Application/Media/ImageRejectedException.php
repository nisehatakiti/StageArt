<?php

declare(strict_types=1);

namespace StageArt\Application\Media;

use RuntimeException;

/** An uploaded image cannot be stored; the message is shown to the user as is. */
final class ImageRejectedException extends RuntimeException
{
}
