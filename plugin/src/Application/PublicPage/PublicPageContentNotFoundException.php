<?php

declare(strict_types=1);

namespace StageArt\Application\PublicPage;

use RuntimeException;

/** A 自由コンテンツ that does not exist, or does not belong to the page it was asked through. */
final class PublicPageContentNotFoundException extends RuntimeException
{
}
