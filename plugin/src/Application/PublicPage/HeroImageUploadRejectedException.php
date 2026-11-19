<?php

declare(strict_types=1);

namespace StageArt\Application\PublicPage;

use RuntimeException;

/** An uploaded hero image failed type/size validation, or WordPress's own upload handling rejected it. */
final class HeroImageUploadRejectedException extends RuntimeException
{
}
