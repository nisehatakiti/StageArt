<?php

declare(strict_types=1);

namespace StageArt\Application\CheckIn;

use RuntimeException;

/**
 * Phase 0-4統合監査 P1-4 (Chapter 31 §3.1 - the attributed Person must be
 * chosen from the target Production's own membership circle). This is a
 * data-integrity check, not an authorization/capability change: it does
 * not alter who may SET an attribution (still CheckInCapability::MANAGE,
 * unchanged), only rejects a syntactically-valid PersonId that has no
 * real relationship to the Production the walk-up sale belongs to.
 */
final class AttributedPersonNotProductionMemberException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The attributed Person is not a member of this Production.');
    }
}
