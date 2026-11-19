<?php

declare(strict_types=1);

namespace StageArt\Application\PublicPage;

use RuntimeException;

/** The Organization / Production the settings belong to does not exist. */
final class PublicPageOwnerNotFoundException extends RuntimeException
{
}
