<?php

declare(strict_types=1);

namespace StageArt\Application\Shared;

/**
 * PR #7 R14-B (design doc §5.2 N7, §7.4): the write broke a UNIQUE key
 * (MySQL 1062) - e.g. a participant row of the same Production, subject
 * and type was stored by a concurrent request after the Use Case's own
 * check. A PersistenceException, so code that only handles the general
 * case still sees a failure; a Use Case that can explain the conflict
 * catches this first and answers 409 (`stageart_participant_type_conflict`)
 * instead of the general 500.
 */
final class UniqueConstraintViolationException extends PersistenceException
{
    public const MYSQL_DUPLICATE_ENTRY = 1062;

    public function __construct(string $operation, string $table)
    {
        parent::__construct($operation, $table, self::MYSQL_DUPLICATE_ENTRY);
    }
}
