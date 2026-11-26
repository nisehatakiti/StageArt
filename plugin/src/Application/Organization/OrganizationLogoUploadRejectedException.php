<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

use RuntimeException;

/** Thrown when an uploaded logo file fails type/size validation, or the
 * underlying WordPress upload handling itself rejects the file. */
final class OrganizationLogoUploadRejectedException extends RuntimeException
{
}
