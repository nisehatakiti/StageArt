<?php

declare(strict_types=1);

namespace StageArt\Application\PublicPage;

use RuntimeException;

/** The requester may not change this page's display settings. */
final class PublicPageAccessDeniedException extends RuntimeException
{
}
